<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Handles HTTP requests for RegisteredUserController operations.
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('universe_id')],
            'password' => ['required', 'string', 'confirmed', 'min:12', 'max:255'],
        ]);

        $accountId = (string) Str::uuid();
        $user = User::create([...$attributes, 'commander_id' => $accountId]);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('activeCommanderUserId', $user->id);

        return redirect()->route('account.universes');
    }
}
