<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Handles HTTP requests for RegisteredUserController operations.
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LicenseKey;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    /**
     * Renders the guest registration form.
     */
    public function create()
    {
        return inertia('Auth/Register');
    }

    /**
     * Validates and creates a user, emits the registration event, then authenticates with a regenerated session.
     *
     * Email is normalized to lowercase and passwords must be confirmed and at least twelve characters long.
     */
    public function store(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'license_key' => ['required', 'string', 'max:64', 'regex:/\AXNOVA(?:-[A-F0-9]{8}){4}\z/i'],
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('universe_id')],
            'password' => ['required', 'string', 'confirmed', 'min:12', 'max:255'],
        ]);

        $licenseKey = Str::upper(trim($attributes['license_key']));
        unset($attributes['license_key']);

        $accountId = (string) Str::uuid();
        $user = DB::transaction(function () use ($attributes, $licenseKey, $accountId): User {
            $key = LicenseKey::query()
                ->where('key_hash', LicenseKey::digest($licenseKey))
                ->lockForUpdate()
                ->first();

            if ($key === null || $key->redeemed_at !== null) {
                throw ValidationException::withMessages([
                    'license_key' => 'La clé de licence est invalide ou a déjà été utilisée.',
                ]);
            }

            $user = User::create([...$attributes, 'commander_id' => $accountId]);
            $redeemedAt = now();
            $redeemed = LicenseKey::query()
                ->whereKey($key->id)
                ->whereNull('redeemed_at')
                ->update([
                    'redeemed_by_user_id' => $user->id,
                    'redeemed_at' => $redeemedAt,
                    'updated_at' => $redeemedAt,
                ]);

            if ($redeemed !== 1) {
                throw ValidationException::withMessages([
                    'license_key' => 'La clé de licence est invalide ou a déjà été utilisée.',
                ]);
            }

            return $user;
        }, 3);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('activeCommanderUserId', $user->id);

        return redirect()->route('verification.notice');
    }
}
