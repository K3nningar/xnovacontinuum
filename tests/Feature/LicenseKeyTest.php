<?php

namespace Tests\Feature;

use App\Models\LicenseKey;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LicenseKeyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const LICENSE_KEY = 'XNOVA-11111111-22222222-33333333-44444444';

    public function test_registration_requires_a_license_key(): void
    {
        $this->from(route('register'))->post(route('register'), [
            'name' => 'Nova Pilot',
            'email' => 'nova@example.test',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
        ])->assertRedirect(route('register'))
            ->assertSessionHasErrors(['license_key' => 'The license key field is required.']);

        $this->assertDatabaseMissing('users', ['email' => 'nova@example.test']);
        $this->assertDatabaseCount('license_keys', 0);
    }

    public function test_registration_rejects_an_unknown_license_key(): void
    {
        $this->from(route('register'))->post(route('register'), [
            'license_key' => self::LICENSE_KEY,
            'name' => 'Nova Pilot',
            'email' => 'nova@example.test',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
        ])->assertRedirect(route('register'))
            ->assertSessionHasErrors(['license_key' => 'La clé de licence est invalide ou a déjà été utilisée.']);

        $this->assertDatabaseMissing('users', ['email' => 'nova@example.test']);
    }

    public function test_several_invalid_license_keys_do_not_lock_out_registration(): void
    {
        $registration = [
            'license_key' => self::LICENSE_KEY,
            'name' => 'Nova Pilot',
            'email' => 'nova@example.test',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
        ];

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
                ->from(route('register'))
                ->post(route('register'), $registration)
                ->assertRedirect(route('register'))
                ->assertSessionHasErrors('license_key');
        }

        $this->assertDatabaseMissing('users', ['email' => 'nova@example.test']);
    }

    public function test_registration_consumes_a_license_key_only_once(): void
    {
        $licenseKey = LicenseKey::query()->create([
            'key_hash' => LicenseKey::digest(self::LICENSE_KEY),
        ]);

        $this->from(route('register'))->post(route('register'), [
            'license_key' => self::LICENSE_KEY,
            'name' => 'Nova Pilot',
            'email' => 'nova@example.test',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
        ])->assertRedirect(route('account.universes'));

        $account = User::query()->where('email', 'nova@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($account);
        $this->assertDatabaseHas('license_keys', [
            'id' => $licenseKey->id,
            'redeemed_by_user_id' => $account->id,
        ]);
        $this->assertNotNull($licenseKey->fresh()->redeemed_at);

        $this->post(route('logout'));
        $this->from(route('register'))->post(route('register'), [
            'license_key' => self::LICENSE_KEY,
            'name' => 'Another Pilot',
            'email' => 'another@example.test',
            'password' => 'another-secure-password',
            'password_confirmation' => 'another-secure-password',
        ])->assertRedirect(route('register'))
            ->assertSessionHasErrors(['license_key' => 'La clé de licence est invalide ou a déjà été utilisée.']);

        $this->assertDatabaseMissing('users', ['email' => 'another@example.test']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_founders_can_generate_keys_that_are_hashed_and_displayed_once(): void
    {
        $founder = User::factory()->create();
        $founder->forceFill(['admin_level' => 4])->save();

        $this->actingAs($founder)->post(route('admin.license-keys.store'), ['quantity' => 2])
            ->assertRedirect(route('admin.license-keys'));

        $generatedKeys = session('generatedLicenseKeys');
        $this->assertCount(2, $generatedKeys);

        $licenseKeys = LicenseKey::query()->orderBy('id')->get();
        $this->assertDatabaseCount('license_keys', 2);
        foreach ($licenseKeys as $index => $licenseKey) {
            $this->assertSame(LicenseKey::digest($generatedKeys[$index]), $licenseKey->key_hash);
            $this->assertSame($founder->id, $licenseKey->issued_by_user_id);
            $this->assertNotSame($generatedKeys[$index], $licenseKey->key_hash);
            $this->assertSame(1, preg_match('/\AXNOVA(?:-[A-F0-9]{8}){4}\z/', $generatedKeys[$index]));
        }

        $this->get(route('admin.license-keys'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/LicenseKeys')
                ->where('generatedKeys', $generatedKeys)
                ->has('licenseKeys.data', 2));

        $this->get(route('admin.license-keys'))
            ->assertInertia(fn (Assert $page) => $page->where('generatedKeys', []));
    }

    public function test_only_founders_can_manage_license_keys(): void
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['admin_level' => 3])->save();

        $this->actingAs($administrator)->get(route('admin.license-keys'))->assertForbidden();
        $this->actingAs($administrator)->post(route('admin.license-keys.store'), ['quantity' => 1])->assertForbidden();

        $this->assertDatabaseCount('license_keys', 0);
    }

    public function test_license_key_generation_rejects_more_than_fifty_keys(): void
    {
        $founder = User::factory()->create();
        $founder->forceFill(['admin_level' => 4])->save();

        $this->actingAs($founder)->from(route('admin.license-keys'))
            ->post(route('admin.license-keys.store'), ['quantity' => 51])
            ->assertRedirect(route('admin.license-keys'))
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('license_keys', 0);
    }
}
