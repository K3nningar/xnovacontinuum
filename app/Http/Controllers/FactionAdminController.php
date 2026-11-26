<?php

namespace App\Http\Controllers;

use App\Models\Faction;
use App\Models\Universe;
use App\Services\AdminAccess;
use App\Services\FactionDiplomacy;
use App\Services\PlanetEconomy;
use App\Services\UnitProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Inertia\Response;

class FactionAdminController extends Controller
{
    private const MAX_FACTION_LOGO_DIMENSION = 1600;
    private const FACTION_LOGO_WEBP_QUALITY = 82;

    public function index(Universe $universe, PlanetEconomy $economy, UnitProductionService $production): Response
    {
        $members = $universe->users()->selectRaw('faction_key, COUNT(*) as total')->groupBy('faction_key')->pluck('total', 'faction_key');
        $factions = $universe->factions()->get()->map(fn (Faction $faction): array => [
            ...$faction->only('id', 'key', 'name', 'description', 'image', 'settings'),
            'members' => (int) ($members[$faction->key] ?? 0),
        ]);
        if (! $factions->contains('key', '0')) {
            $factions->prepend(['key' => '0', 'name' => 'Faction 0', 'members' => (int) ($members['0'] ?? 0)]);
        }

        return inertia('Admin/Factions', [
            'universe' => $universe->only('id', 'name', 'status'),
            'factions' => $factions->sortBy(fn (array $faction): int => $faction['key'] === '0' ? 0 : 1)->values(),
            'relations' => DB::table('faction_diplomacy')->where('universe_id', $universe->id)->get(['first_faction', 'second_faction', 'status']),
            'catalog' => [
                'buildings' => collect($economy->administrativeBuildings())->map(fn (array $building, string $key): array => [
                    'key' => $key, 'code' => $building['code'], 'translationKey' => $key, 'name' => $building['label'],
                ])->values(),
                ...collect($production->administrativeUnits())->map(fn (array $units) => collect($units)
                    ->map(fn (array $unit, int $code): array => [
                        'key' => (string) $code, 'code' => $code, 'translationKey' => $unit['key'], 'name' => $unit['name'],
                    ])->values())->all(),
            ],
        ]);
    }

    public function store(Request $request, Universe $universe): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_-]*$/', Rule::unique('factions', 'key')->where('universe_id', $universe->id)],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'string', 'max:255', 'regex:~^(?:/(?!/)[A-Za-z0-9_./-]+|https?://[^\s]+)$~'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);
        $data['image'] = $this->uploadedImageUrl($request, $universe, $data['key']) ?? ($data['image'] ?? null);
        unset($data['image_file']);

        DB::transaction(function () use ($universe, $data, $request): void {
            $universe->factions()->create($data);
            $this->audit($request, $universe, 'faction_created', ['key' => $data['key'], 'name' => $data['name']]);
        });

        return back()->with('success', 'Faction créée.');
    }

    public function diplomacy(Request $request, Universe $universe, FactionDiplomacy $diplomacy): RedirectResponse
    {
        $data = $request->validate([
            'first' => ['required', 'string', 'max:40'],
            'second' => ['required', 'string', 'max:40', 'different:first'],
            'status' => ['required', Rule::in(['war', 'neutral', 'allied'])],
        ]);
        $keys = $universe->factions()->pluck('key')->push('0');
        if (! $keys->contains($data['first']) || ! $keys->contains($data['second'])) {
            throw ValidationException::withMessages(['first' => 'Faction inconnue dans cet univers.']);
        }
        [$first, $second] = $diplomacy->orderedKeys($data['first'], $data['second']);
        DB::transaction(function () use ($universe, $first, $second, $data, $request): void {
            DB::table('faction_diplomacy')->updateOrInsert(
                ['universe_id' => $universe->id, 'first_faction' => $first, 'second_faction' => $second],
                ['status' => $data['status'], 'updated_at' => now(), 'created_at' => now()],
            );
            $this->audit($request, $universe, 'faction_diplomacy_changed', ['first' => $first, 'second' => $second, 'status' => $data['status']]);
        });

        return back()->with('success', 'Diplomatie mise à jour.');
    }

    public function update(Request $request, Universe $universe, Faction $faction): RedirectResponse
    {
        abort_unless((int) $faction->universe_id === (int) $universe->id, 404);

        return $this->saveSettings($request, $universe, $faction);
    }

    public function updateTemplate(Request $request, Universe $universe): RedirectResponse
    {
        return $this->saveSettings($request, $universe);
    }

    private function saveSettings(Request $request, Universe $universe, ?Faction $faction = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'string', 'max:255', 'regex:~^(?:/(?!/)[A-Za-z0-9_./-]+|https?://[^\s]+)$~'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'settings' => ['required', 'json'],
        ]);
        $imageFileUrl = $this->uploadedImageUrl($request, $universe, $faction?->key ?? '0');
        $oldImage = ($faction ?? $universe->factions()->where('key', '0')->first())?->image;
        if ($imageFileUrl !== null) {
            $data['image'] = $imageFileUrl;
        }
        unset($data['image_file']);
        $settings = json_decode($data['settings'], true);
        unset($data['settings']);
        $rules = [
            'settings' => ['present', 'array:buildings,ships,defences,statistics'],
            'settings.statistics' => ['array:attack,shield,hull'],
            'settings.statistics.*' => ['numeric', 'between:0.1,10'],
        ];
        foreach (['buildings', 'ships', 'defences'] as $category) {
            $rules["settings.$category"] = ['array'];
            $rules["settings.$category.*"] = ['array:available,name,image'];
            $rules["settings.$category.*.available"] = ['boolean'];
            $rules["settings.$category.*.name"] = ['string', 'max:80'];
            $rules["settings.$category.*.image"] = ['string', 'max:255', 'regex:~^(?:/(?!/)[A-Za-z0-9_./-]+|https?://[^\s]+)$~'];
        }
        Validator::make(['settings' => $settings], $rules)->validate();
        foreach (['buildings', 'ships', 'defences'] as $category) {
            foreach ($settings[$category] ?? [] as $key => $element) {
                if (array_key_exists('available', $element)) {
                    $settings[$category][$key]['available'] = (bool) $element['available'];
                }
            }
        }
        DB::transaction(function () use ($faction, $data, $settings, $request, $universe, $imageFileUrl, $oldImage): void {
            $faction ??= $universe->factions()->firstOrCreate(['key' => '0'], ['name' => 'Faction 0']);
            $faction->update([...$data, 'settings' => $settings]);
            if ($imageFileUrl !== null && $oldImage && $oldImage !== $imageFileUrl) {
                $this->deleteUploadedImage($oldImage);
            }
            $this->audit($request, $universe, 'faction_updated', ['key' => $faction->key, 'name' => $data['name'], 'settings' => $settings]);
        });

        return back()->with('success', 'Faction mise à jour.');
    }

    private function uploadedImageUrl(Request $request, Universe $universe, string $key): ?string
    {
        if (! $request->hasFile('image_file')) {
            return null;
        }

        $file = $request->file('image_file');
        $contents = $this->convertToWebp($file);
        $path = 'factions/'.$universe->id.'/'.$key.'-'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, $contents);

        // Keep the value portable between local, staging and production hosts.
        return '/storage/'.ltrim($path, '/');
    }

    private function convertToWebp(\Illuminate\Http\UploadedFile $file): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw ValidationException::withMessages(['image_file' => 'La conversion WebP n’est pas disponible sur ce serveur.']);
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw ValidationException::withMessages(['image_file' => 'Cette image ne peut pas être convertie en WebP.']);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_FACTION_LOGO_DIMENSION / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        $encoded = imagewebp($canvas, null, self::FACTION_LOGO_WEBP_QUALITY);
        $contents = ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);

        if (! $encoded || $contents === false || $contents === '') {
            throw ValidationException::withMessages(['image_file' => 'Cette image ne peut pas être encodée en WebP.']);
        }

        return $contents;
    }

    private function deleteUploadedImage(string $image): void
    {
        $path = parse_url($image, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, '/storage/factions/')) {
            return;
        }

        Storage::disk('public')->delete(ltrim(str_replace('/storage/', '', $path), '/'));
    }

    private function audit(Request $request, Universe $universe, string $action, array $details): void
    {
        DB::table('admin_audit_logs')->insert([
            'actor_id' => $request->user()->id,
            'actor_level' => app(AdminAccess::class)->levelFor($request->user(), $universe->id),
            'universe_id' => $universe->id,
            'action' => $action,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
