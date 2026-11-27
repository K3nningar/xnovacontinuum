<?php

namespace Tests\Feature;

use App\Models\LicenseKey;
use App\Models\Universe;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const LICENSE_KEY = 'XNOVA-11111111-22222222-33333333-44444444';

    public function test_registration_sends_a_confirmation_and_blocks_game_access_until_verified(): void
    {
        Notification::fake();
        LicenseKey::query()->create(['key_hash' => LicenseKey::digest(self::LICENSE_KEY)]);

        $this->post(route('register'), [
            'license_key' => self::LICENSE_KEY,
            'name' => 'Nova Pilot',
            'email' => 'nova@example.test',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
        ])->assertRedirect(route('verification.notice'));

        $account = User::query()->where('email', 'nova@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($account);
        $this->assertNull($account->email_verified_at);
        Notification::assertSentTo($account, VerifyEmail::class);

        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/VerifyEmail')
                ->where('email', 'nova@example.test')
                ->where('status', null));
    }

    public function test_a_signed_confirmation_verifies_the_account_and_restores_access(): void
    {
        $account = User::factory()->unverified()->create();
        Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);

        $this->actingAs($account)->get($this->verificationUrl($account))
            ->assertRedirect(route('account.universes'));

        $this->assertNotNull($account->fresh()->email_verified_at);
        $this->get(route('account.universes'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Universes'));
    }

    public function test_a_signed_link_with_a_different_email_hash_is_rejected(): void
    {
        $account = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $account->id,
            'hash' => sha1('different@example.test'),
        ]);

        $this->actingAs($account)->get($url)->assertForbidden();

        $this->assertNull($account->fresh()->email_verified_at);
    }

    public function test_an_unverified_commander_can_request_another_confirmation_link(): void
    {
        Notification::fake();
        $account = User::factory()->unverified()->create();

        $this->actingAs($account)
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($account, VerifyEmail::class);
    }

    public function test_a_profile_inherits_the_commander_accounts_verified_state(): void
    {
        $account = User::factory()->create();
        $universe = Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);
        $profile = new User;
        $profile->name = $account->name;
        $profile->email = null;
        $profile->password = 'universe-profile-password';
        $profile->forceFill([
            'commander_id' => $account->commander_id,
            'universe_id' => $universe->id,
        ])->save();

        $this->actingAs($profile)->get(route('account.universes'))->assertOk();
        $this->assertAuthenticatedAs($account);
    }

    public function test_a_profile_cannot_bypass_its_unverified_commander_account(): void
    {
        $account = User::factory()->unverified()->create();
        $universe = Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);
        $profile = new User;
        $profile->name = $account->name;
        $profile->email = null;
        $profile->password = 'universe-profile-password';
        $profile->forceFill([
            'commander_id' => $account->commander_id,
            'universe_id' => $universe->id,
            'email_verified_at' => now(),
        ])->save();

        $this->actingAs($profile)->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_logging_in_with_an_unverified_account_leads_to_confirmation(): void
    {
        $account = User::factory()->unverified()->create([
            'email' => 'unverified@example.test',
            'password' => 'secure-password-123',
        ]);

        $this->post(route('login'), [
            'email' => 'unverified@example.test',
            'password' => 'secure-password-123',
        ])->assertRedirect(route('verification.notice'));

        $this->assertAuthenticatedAs($account);
    }

    private function verificationUrl(User $account): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $account->id,
            'hash' => sha1($account->getEmailForVerification()),
        ]);
    }
}
