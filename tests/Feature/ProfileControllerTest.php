<?php

namespace Tests\Feature;

use App\Models\Universe;
use App\Models\User;
use App\Services\TotpAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_profile(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));
    }

    public function test_player_can_update_account_password_from_any_universe_profile(): void
    {
        $account = User::factory()->create(['email' => 'profile@example.test', 'password' => 'original-password']);
        $profile = $this->createGameProfile($account);

        $this->actingAs($profile)->patch(route('profile.password'), [
            'current_password' => 'original-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('new-secure-password', $account->fresh()->password));
        $this->assertFalse(Hash::check('new-secure-password', $profile->fresh()->password));
    }

    public function test_password_change_rejects_incorrect_current_password_and_confirmation(): void
    {
        $account = User::factory()->create(['password' => 'original-password']);

        $this->actingAs($account)->patch(route('profile.password'), [
            'current_password' => 'wrong-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('original-password', $account->fresh()->password));
    }

    public function test_player_can_upload_and_remove_an_avatar(): void
    {
        Storage::fake('public');
        $account = User::factory()->create();

        $this->actingAs($account)->post(route('profile.avatar'), [
            'avatar' => UploadedFile::fake()->image('pilot.png', 128, 128),
        ])->assertRedirect();

        $path = $account->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->delete(route('profile.avatar.destroy'))->assertRedirect();
        $this->assertNull($account->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_avatar_upload_rejects_non_images(): void
    {
        Storage::fake('public');
        $account = User::factory()->create();

        $this->actingAs($account)->post(route('profile.avatar'), [
            'avatar' => UploadedFile::fake()->create('script.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('avatar');

        $this->assertNull($account->fresh()->avatar_path);
    }

    public function test_uploaded_avatar_urls_use_the_https_request_host_not_localhost(): void
    {
        $account = User::factory()->create(['avatar_path' => 'avatars/test-player/avatar.png']);
        $profile = $this->createGameProfile($account);
        config(['filesystems.disks.public.driver' => 'local']);

        URL::forceRootUrl('https://xnovalaravel.test');
        URL::forceScheme('https');
        $this->actingAs($profile)->get(route('profile.show'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('profile.avatarUrl', 'https://xnovalaravel.test/storage/avatars/test-player/avatar.png')
            ->where('auth.user.avatar_url', 'https://xnovalaravel.test/storage/avatars/test-player/avatar.png')
            ->where('auth.user.avatar_url', fn (string $url): bool => ! str_contains($url, 'localhost')));

        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.avatar_url', 'https://xnovalaravel.test/storage/avatars/test-player/avatar.png'));

    }

    public function test_profile_page_shows_achievements_as_in_development(): void
    {
        $account = User::factory()->create();

        $this->actingAs($account)->get(route('profile.show'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('profile.email', $account->email)
            ->where('profile.twoFactorEnabled', false)
            ->where('profile.pendingTwoFactorSecret', null));
    }

    public function test_player_can_enable_two_factor_and_must_use_a_code_at_login(): void
    {
        $account = User::factory()->create([
            'email' => 'two-factor@example.test',
            'password' => 'original-password',
        ]);
        $totp = app(TotpAuthenticator::class);
        $this->travelTo('2026-11-20 12:00:00');

        $this->actingAs($account)->post(route('profile.two-factor.setup'))->assertRedirect();
        $this->get(route('profile.show'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('profile.twoFactorEnabled', false)
            ->has('profile.pendingTwoFactorSecret'));
        $secret = session('profile.pending_two_factor_secret');

        $this->post(route('profile.two-factor.enable'), ['code' => $totp->currentCode($secret)])->assertRedirect();
        $this->assertNotNull($account->fresh()->two_factor_enabled_at);

        $this->post(route('logout'));
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('auth.twoFactorChallenge', false));

        $this->post(route('login'), [
            'email' => 'two-factor@example.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('auth.twoFactorChallenge', false));

        $this->post(route('login'), [
            'email' => 'two-factor@example.test',
            'password' => 'original-password',
            'remember' => true,
        ])->assertRedirect();
        $this->assertGuest();
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('auth.twoFactorChallenge', true));
        $this->get(route('login', ['restart_two_factor' => 1]))->assertRedirect(route('login'));
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('auth.twoFactorChallenge', false));
        $this->post(route('login'), ['code' => '123456'])->assertSessionHasErrors('email');

        $this->post(route('login'), [
            'email' => 'two-factor@example.test',
            'password' => 'original-password',
            'remember' => true,
        ])->assertRedirect();

        $this->post(route('login'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertSame(true, session('auth.two_factor_challenge'));

        $this->post(route('login'), [
            'email' => 'two-factor@example.test',
            'password' => null,
            'code' => $totp->currentCode($account->fresh()->two_factor_secret),
        ])->assertRedirect(route('account.universes'));
        $this->assertAuthenticatedAs($account);
        $this->assertTrue(session('auth.remember'));
    }

    public function test_player_can_disable_two_factor_with_password_and_code(): void
    {
        $totp = app(TotpAuthenticator::class);
        $secret = 'JBSWY3DPEHPK3PXP';
        $account = User::factory()->create([
            'password' => 'original-password',
            'two_factor_secret' => $secret,
            'two_factor_enabled_at' => now(),
        ]);

        $this->actingAs($account)->delete(route('profile.two-factor'), [
            'current_password' => 'wrong-password',
            'code' => $totp->currentCode($secret),
        ])->assertSessionHasErrors('code');
        $this->assertNotNull($account->fresh()->two_factor_enabled_at);

        $this->delete(route('profile.two-factor'), [
            'current_password' => 'original-password',
            'code' => $totp->currentCode($secret),
        ])->assertRedirect();
        $this->assertNull($account->fresh()->two_factor_enabled_at);
    }

    public function test_totp_rejects_the_previous_period_code(): void
    {
        $totp = app(TotpAuthenticator::class);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->travelTo('2026-11-20 12:00:00');

        $this->assertFalse($totp->verify($secret, $totp->currentCode($secret, now()->timestamp - 30)));
        $this->assertTrue($totp->verify($secret, $totp->currentCode($secret, now()->timestamp)));
    }

    public function test_two_factor_challenge_expires_after_five_minutes(): void
    {
        $account = User::factory()->create([
            'email' => 'expired-factor@example.test',
            'password' => 'original-password',
            'two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'two_factor_enabled_at' => now(),
        ]);
        $totp = app(TotpAuthenticator::class);
        $this->travelTo('2026-11-20 12:00:00');

        $this->post(route('login'), [
            'email' => 'expired-factor@example.test',
            'password' => 'original-password',
        ])->assertRedirect();

        $this->travel(6)->minutes();
        $this->post(route('login'), ['code' => $totp->currentCode($account->two_factor_secret)])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('auth.twoFactorChallenge', false));
    }

    public function test_totp_matches_the_standard_hmac_sha1_test_vector(): void
    {
        $totp = app(TotpAuthenticator::class);

        $this->assertSame('287082', $totp->currentCode('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59));
        $this->assertTrue($totp->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287082', 59));
        $this->assertFalse($totp->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287083', 59));
    }

    private function createGameProfile(User $account): User
    {
        $universe = Universe::create(['name' => 'Profile test universe', 'slug' => 'profile-test-universe']);
        $profile = new User;
        $profile->name = $account->name;
        $profile->email = null;
        $profile->password = 'profile-password';
        $profile->forceFill(['commander_id' => $account->commander_id, 'universe_id' => $universe->id])->save();

        return $profile;
    }
}
