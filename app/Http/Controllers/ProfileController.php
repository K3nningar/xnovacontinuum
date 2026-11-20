<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PublicAvatarUrl;
use App\Services\TotpAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request, PublicAvatarUrl $avatarUrl): Response
    {
        $account = $this->accountFor($request);
        $pendingSecret = $request->session()->get('profile.pending_two_factor_secret');

        return inertia('Profile/Show', [
            'profile' => [
                'name' => $account->name,
                'email' => $account->email,
                'avatarUrl' => $account->avatar_path ? $avatarUrl->for($request, $account->avatar_path) : null,
                'twoFactorEnabled' => $account->two_factor_enabled_at !== null,
                'emailVerified' => $account->email_verified_at !== null,
                'pendingTwoFactorSecret' => $account->two_factor_enabled_at === null ? $pendingSecret : null,
                'pendingTwoFactorUri' => $account->two_factor_enabled_at === null && $pendingSecret
                    ? app(TotpAuthenticator::class)->provisioningUri($pendingSecret, $account->email)
                    : null,
            ],
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $account = $this->accountFor($request);
        $attributes = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'min:12', 'max:255'],
        ]);

        if (! Hash::check($attributes['current_password'], $account->password)) {
            throw ValidationException::withMessages(['current_password' => __('The current password is incorrect.')]);
        }

        $account->update(['password' => $attributes['password']]);

        return back()->with('success', __('Password updated.'));
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $account = $this->accountFor($request);
        $attributes = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=2048,max_height=2048'],
        ]);

        $path = $attributes['avatar']->store('avatars/'.$account->commander_id, 'public');
        $oldPath = $account->avatar_path;
        $account->forceFill(['avatar_path' => $path])->save();

        if ($oldPath && str_starts_with($oldPath, 'avatars/'.$account->commander_id.'/')) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('success', __('Avatar updated.'));
    }

    public function deleteAvatar(Request $request): RedirectResponse
    {
        $account = $this->accountFor($request);
        $oldPath = $account->avatar_path;
        $account->forceFill(['avatar_path' => null])->save();

        if ($oldPath && str_starts_with($oldPath, 'avatars/'.$account->commander_id.'/')) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('success', __('Avatar removed.'));
    }

    public function beginTwoFactorSetup(Request $request, TotpAuthenticator $totp): RedirectResponse
    {
        $account = $this->accountFor($request);
        if ($account->two_factor_enabled_at) {
            return back()->with('error', __('Two-factor authentication is already enabled.'));
        }

        $request->session()->put('profile.pending_two_factor_secret', $totp->generateSecret());

        return back()->with('success', __('Scan or enter the authenticator key, then confirm with a code.'));
    }

    public function enableTwoFactor(Request $request, TotpAuthenticator $totp): RedirectResponse
    {
        $account = $this->accountFor($request);
        $attributes = $request->validate(['code' => ['required', 'digits:6']]);
        $secret = $request->session()->get('profile.pending_two_factor_secret');

        if (! $secret || ! $totp->verify($secret, $attributes['code'])) {
            if ($secret) {
                $request->session()->forget('profile.pending_two_factor_secret');
            }

            throw ValidationException::withMessages(['code' => __('The authenticator code is invalid. Start setup again if the key has expired.')]);
        }

        $account->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_enabled_at' => now(),
        ])->save();
        $request->session()->forget('profile.pending_two_factor_secret');

        return back()->with('success', __('Two-factor authentication enabled.'));
    }

    public function disableTwoFactor(Request $request, TotpAuthenticator $totp): RedirectResponse
    {
        $account = $this->accountFor($request);
        $attributes = $request->validate([
            'current_password' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);

        if (! Hash::check($attributes['current_password'], $account->password)
            || ! $account->two_factor_secret
            || ! $totp->verify($account->two_factor_secret, $attributes['code'])) {
            throw ValidationException::withMessages(['code' => __('The password or authenticator code is incorrect.')]);
        }

        $account->forceFill(['two_factor_secret' => null, 'two_factor_enabled_at' => null])->save();

        return back()->with('success', __('Two-factor authentication disabled.'));
    }

    private function accountFor(Request $request): User
    {
        return User::query()->where('commander_id', $request->user()->commander_id)
            ->whereNull('universe_id')->firstOrFail();
    }
}
