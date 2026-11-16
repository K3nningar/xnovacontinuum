<?php

namespace App\Http\Controllers;

use App\Services\Changelog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Response;

class ChangelogController extends Controller
{
    public function __construct(private Changelog $changelog) {}

    public function welcome(): Response
    {
        return inertia('Welcome', ['releases' => $this->releases(true)]);
    }

    public function index(): Response
    {
        return inertia('Changelog', ['releases' => $this->releases(true), 'administration' => false]);
    }

    public function administration(): Response
    {
        return inertia('Changelog', ['releases' => $this->releases(false), 'administration' => true]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string', Rule::in(array_column($this->changelog->definitions(), 'version'))],
            'published' => ['required', 'boolean'],
        ]);
        $published = (bool) $data['published'];

        DB::transaction(function () use ($request, $data, $published): void {
            DB::table('changelog_release_states')->upsert([
                'version' => $data['version'],
                'published' => $published,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['version'], ['published', 'updated_at']);
            DB::table('admin_audit_logs')->insert([
                'actor_id' => $request->user()->id,
                'actor_level' => $request->user()->admin_level,
                'target_user_id' => null,
                'planet_id' => null,
                'action' => 'changelog_publication_changed',
                'details' => json_encode(['version' => $data['version'], 'published' => $published], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);

        return redirect()->route('admin.changelog');
    }

    public function storeEntry(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string', Rule::in(array_column($this->changelog->definitions(), 'version'))],
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', Rule::in(['CHANGE', 'HOTFIX', 'KNOWN ISSUE', 'NEW'])],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $entryId = DB::table('changelog_entries')->insertGetId([
                ...$data,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('admin_audit_logs')->insert([
                'actor_id' => $request->user()->id,
                'actor_level' => $request->user()->admin_level,
                'target_user_id' => null,
                'planet_id' => null,
                'action' => 'changelog_entry_added',
                'details' => json_encode(['entry_id' => $entryId, 'version' => $data['version']], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);

        return redirect()->route('admin.changelog');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function releases(bool $publishedOnly): array
    {
        $publishedVersions = DB::table('changelog_release_states')
            ->where('published', true)->pluck('version')->all();
        $releases = array_map(fn (array $release): array => [
            ...$release,
            'published' => in_array($release['version'], $publishedVersions, true),
        ], $this->changelog->definitions());

        if ($publishedOnly) {
            $releases = array_values(array_filter($releases, fn (array $release): bool => $release['published']));
        }

        $entries = DB::table('changelog_entries')
            ->whereIn('version', array_column($releases, 'version'))
            ->orderByDesc('date')->orderByDesc('id')
            ->get(['id', 'version', 'date', 'type', 'description'])
            ->groupBy('version');

        foreach ($releases as &$release) {
            $manualEntries = $entries->get($release['version'], collect())->map(fn (object $entry): array => [
                'key' => 'manual-'.$entry->id,
                'date' => $entry->date,
                'type' => $entry->type,
                'text' => $entry->description,
            ])->all();
            $release['entries'] = array_merge($manualEntries, $release['entries']);
            usort($release['entries'], fn (array $a, array $b): int => strcmp($b['date'], $a['date']));
        }
        unset($release);

        return $releases;
    }
}
