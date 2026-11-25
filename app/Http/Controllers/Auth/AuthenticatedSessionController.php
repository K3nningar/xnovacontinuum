<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Handles HTTP requests for AuthenticatedSessionController operations.
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TotpAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Renders the guest login form.
     */
    public function create(Request $request)
    {
        if ($request->boolean('restart_two_factor')
            || ((bool) $request->session()->get('auth.two_factor_challenge', false)
                && (int) $request->session()->get('auth.two_factor_started_at', 0) < now()->subMinutes(5)->timestamp)) {
            $request->session()->forget([
                'auth.two_factor_user_id', 'auth.two_factor_started_at', 'auth.two_factor_challenge', 'auth.two_factor_remember',
            ]);
            $request->session()->save();

            return redirect()->route('login');
        }

        return inertia('Auth/Login');
    }

    /**
     * Authenticates validated credentials, rejects active suspensions, and regenerates the session identifier.
     *
     * Successful logins honor the remember flag and intended destination; failed credentials or suspension
     * invalidate the session state before returning a validation error.
     */
    public function store(Request $request, TotpAuthenticator $totp): RedirectResponse
    {
        if ((bool) $request->session()->get('auth.two_factor_challenge', false)) {
            return $this->completeTwoFactorChallenge($request, $totp);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Les identifiants fournis sont incorrects.']);
        }

        $user = $request->user();
        $account = User::query()->where('commander_id', $user->commander_id)->whereNull('universe_id')->first();
        if ($user->isSuspended() || $account?->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages(['email' => 'Ce compte est temporairement suspendu.']);
        }

        if ($account?->two_factor_enabled_at) {
            $request->session()->put('auth.two_factor_user_id', $account->id);
            $request->session()->put('auth.two_factor_remember', $request->boolean('remember'));
            $request->session()->put('auth.two_factor_challenge', true);
            $request->session()->put('auth.two_factor_started_at', now()->timestamp);
            Auth::logout();
            $request->session()->regenerate();

            return back()->with('success', 'Mot de passe vérifié. Saisissez votre code d’authentification.');
        }

        return $this->completeLogin($request, $user, $request->boolean('remember'));
    }

    private function completeTwoFactorChallenge(Request $request, TotpAuthenticator $totp): RedirectResponse
    {
        $attributes = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);
        $code = $attributes['code'];
        $userId = (int) $request->session()->get('auth.two_factor_user_id', 0);
        $startedAt = (int) $request->session()->get('auth.two_factor_started_at', 0);
        $account = User::query()->whereKey($userId)->whereNull('universe_id')->first();

        if (! $account?->two_factor_enabled_at || ! $account->two_factor_secret) {
            $request->session()->forget(['auth.two_factor_user_id', 'auth.two_factor_started_at', 'auth.two_factor_challenge', 'auth.two_factor_remember']);

            throw ValidationException::withMessages(['code' => 'L’authentification à deux facteurs n’est plus configurée. Recommencez la connexion.']);
        }

        if ($startedAt < now()->subMinutes(5)->timestamp) {
            $request->session()->forget(['auth.two_factor_user_id', 'auth.two_factor_started_at', 'auth.two_factor_challenge', 'auth.two_factor_remember']);

            throw ValidationException::withMessages(['code' => 'La vérification a expiré. Recommencez la connexion.']);
        }

        if (! $totp->verify($account->two_factor_secret, $code)) {
            throw ValidationException::withMessages(['code' => 'Le code d’authentification à deux facteurs est invalide ou expiré.']);
        }

        if ($account->isSuspended()) {
            $request->session()->forget(['auth.two_factor_user_id', 'auth.two_factor_started_at', 'auth.two_factor_challenge']);

            throw ValidationException::withMessages(['email' => 'Ce compte est temporairement suspendu.']);
        }

        $remember = $request->session()->pull('auth.two_factor_remember', false);

        return $this->completeLogin($request, $account, (bool) $remember);
    }

    private function completeLogin(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('auth.remember', $remember);
        $request->session()->put('activeCommanderUserId', $user->id);
        $request->session()->forget([
            'auth.two_factor_user_id', 'auth.two_factor_started_at', 'auth.two_factor_challenge', 'auth.two_factor_remember',
            'onboardingUniverseId', 'onboardingCommanderId', 'onboardingCommanderUserId',
        ]);

        return redirect()->route('account.universes');
    }

    /**
     * Logs out the current user, invalidates the session, rotates its CSRF token, and returns to the public home page.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
