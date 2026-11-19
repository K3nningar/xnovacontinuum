<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Handles HTTP requests for AuthenticatedSessionController operations.
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Renders the guest login form.
     */
    public function create()
    {
        return inertia('Auth/Login');
    }

    /**
     * Authenticates validated credentials, rejects active suspensions, and regenerates the session identifier.
     *
     * Successful logins honor the remember flag and intended destination; failed credentials or suspension
     * invalidate the session state before returning a validation error.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Les identifiants fournis sont incorrects.',
            ]);
        }

        if ($request->user()->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Ce compte est temporairement suspendu.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
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
