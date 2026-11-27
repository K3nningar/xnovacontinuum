<?php

namespace App\Http\Controllers;

use App\Models\LicenseKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class AdminLicenseKeyController extends Controller
{
    /**
     * Displays key issuance history and any keys generated during the preceding request.
     */
    public function index(): Response
    {
        $licenseKeys = LicenseKey::query()
            ->with('redeemedBy:id,name,email')
            ->orderByDesc('id')
            ->paginate(25)
            ->through(fn (LicenseKey $licenseKey): array => [
                'id' => $licenseKey->id,
                'created_at' => $licenseKey->created_at?->toIso8601String(),
                'redeemed_at' => $licenseKey->redeemed_at?->toIso8601String(),
                'redeemed_by' => $licenseKey->redeemedBy?->email,
            ]);

        return inertia('Admin/LicenseKeys', [
            'licenseKeys' => $licenseKeys,
            'generatedKeys' => request()->session()->pull('generatedLicenseKeys', []),
        ]);
    }

    /**
     * Issues a bounded batch of cryptographically random, single-use license keys.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'between:1,50'],
        ]);

        $generatedKeys = DB::transaction(function () use ($request, $data): array {
            $generatedKeys = [];

            for ($index = 0; $index < (int) $data['quantity']; $index++) {
                $plainText = LicenseKey::generateCode();

                LicenseKey::query()->create([
                    'key_hash' => LicenseKey::digest($plainText),
                    'issued_by_user_id' => $request->user()->id,
                ]);

                $generatedKeys[] = $plainText;
            }

            return $generatedKeys;
        }, 3);

        return redirect()->route('admin.license-keys')
            ->with('generatedLicenseKeys', $generatedKeys)
            ->with('success', 'Clés de licence générées. Copiez-les maintenant : elles ne seront affichées qu’une seule fois.');
    }
}
