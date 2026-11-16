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

        return $publishedOnly
            ? array_values(array_filter($releases, fn (array $release): bool => $release['published']))
            : $releases;
    }
}
