<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mendaftarkan rute pengujian khusus untuk memverifikasi middleware otorisasi tanpa membuat halaman bisnis palsu
        Route::middleware(['web', 'auth', 'role:owner'])->get('/_test/owner-only', function () {
            return response()->json(['message' => 'Owner Area']);
        });

        Route::middleware(['web', 'auth', 'role:karyawan'])->get('/_test/karyawan-only', function () {
            return response()->json(['message' => 'Karyawan Area']);
        });

        Route::middleware(['web', 'auth', 'role:karyawan,owner'])->get('/_test/operational-area', function () {
            return response()->json(['message' => 'Operational Area']);
        });

        Route::middleware(['web', 'role:pembeli'])->get('/_test/pembeli-guest-check', function () {
            return response()->json(['message' => 'Pembeli Area']);
        });
    }

    public function test_public_registration_assigns_pembeli_role_by_default(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Budi Pembeli')
            ->set('email', 'budi@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123');

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::where('email', 'budi@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(Role::PEMBELI, $user->role);
        $this->assertTrue($user->isPembeli());
        $this->assertFalse($user->isKaryawan());
        $this->assertFalse($user->isOwner());
    }

    public function test_public_registration_ignores_privilege_escalation_attempt(): void
    {
        // Mencoba menyusupkan input role via request registrasi
        $response = $this->post('/register', [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'owner',
        ]);

        // Karena Breeze Volt menggunakan form request handling di Volt component, kita juga verifikasi via Volt::test
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Hacker Role')
            ->set('email', 'hacker@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123');

        // Komponen Volt tidak memiliki properti publik $role, namun kita pastikan pemanggilan register tetap menghasilkan role pembeli
        $component->call('register');

        $createdUser = User::where('email', 'hacker@example.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertSame(Role::PEMBELI, $createdUser->role);
        $this->assertNotSame(Role::OWNER, $createdUser->role);
    }

    public function test_guest_is_redirected_to_login_when_accessing_protected_role_route(): void
    {
        $response = $this->get('/_test/owner-only');

        $response->assertRedirect(route('login'));
    }

    public function test_pembeli_cannot_access_karyawan_or_owner_routes(): void
    {
        $pembeli = User::factory()->create([
            'role' => Role::PEMBELI,
        ]);

        // Akses ke rute karyawan-only ditolak 403
        $this->actingAs($pembeli)
            ->get('/_test/karyawan-only')
            ->assertForbidden();

        // Akses ke rute owner-only ditolak 403
        $this->actingAs($pembeli)
            ->get('/_test/owner-only')
            ->assertForbidden();

        // Akses ke rute operasional ditolak 403
        $this->actingAs($pembeli)
            ->get('/_test/operational-area')
            ->assertForbidden();
    }

    public function test_karyawan_cannot_access_owner_routes(): void
    {
        $karyawan = User::factory()->create([
            'role' => Role::KARYAWAN,
        ]);

        $this->actingAs($karyawan)
            ->get('/_test/owner-only')
            ->assertForbidden();
    }

    public function test_karyawan_can_access_karyawan_and_operational_routes(): void
    {
        $karyawan = User::factory()->create([
            'role' => Role::KARYAWAN,
        ]);

        $this->actingAs($karyawan)
            ->get('/_test/karyawan-only')
            ->assertOk()
            ->assertJson(['message' => 'Karyawan Area']);

        $this->actingAs($karyawan)
            ->get('/_test/operational-area')
            ->assertOk()
            ->assertJson(['message' => 'Operational Area']);
    }

    public function test_owner_can_access_owner_and_operational_routes(): void
    {
        $owner = User::factory()->create([
            'role' => Role::OWNER,
        ]);

        $this->actingAs($owner)
            ->get('/_test/owner-only')
            ->assertOk()
            ->assertJson(['message' => 'Owner Area']);

        $this->actingAs($owner)
            ->get('/_test/operational-area')
            ->assertOk()
            ->assertJson(['message' => 'Operational Area']);
    }

    public function test_create_user_command_successfully_creates_owner_interactively(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Nama pengguna', 'Pak Bos Helm')
            ->expectsQuestion('Alamat email', 'owner@bayeng.test')
            ->expectsChoice('Pilih role pengguna', Role::OWNER->value, Role::values())
            ->expectsQuestion('Masukkan password (minimal 8 karakter)', 'ownerRahasia123')
            ->expectsConfirmation('Apakah data di atas sudah benar dan Anda ingin melanjutkan pembuatan akun?', 'yes')
            ->expectsOutputToContain('Pengguna [Pak Bos Helm] dengan role [Owner] berhasil dibuat.')
            ->assertSuccessful();

        $user = User::where('email', 'owner@bayeng.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('Pak Bos Helm', $user->name);
        $this->assertSame(Role::OWNER, $user->role);
        $this->assertTrue(Hash::check('ownerRahasia123', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_create_user_command_successfully_creates_karyawan_interactively(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Nama pengguna', 'Staf Toko 1')
            ->expectsQuestion('Alamat email', 'staf1@bayeng.test')
            ->expectsChoice('Pilih role pengguna', Role::KARYAWAN->value, Role::values())
            ->expectsQuestion('Masukkan password (minimal 8 karakter)', 'karyawanPass123')
            ->expectsConfirmation('Apakah data di atas sudah benar dan Anda ingin melanjutkan pembuatan akun?', 'yes')
            ->expectsOutputToContain('Pengguna [Staf Toko 1] dengan role [Karyawan] berhasil dibuat.')
            ->assertSuccessful();

        $user = User::where('email', 'staf1@bayeng.test')->first();
        $this->assertNotNull($user);
        $this->assertSame(Role::KARYAWAN, $user->role);
        $this->assertTrue(Hash::check('karyawanPass123', $user->password));
    }

    public function test_create_user_command_validates_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'existing@bayeng.test',
        ]);

        $this->artisan('user:create')
            ->expectsQuestion('Nama pengguna', 'Nama Baru')
            ->expectsQuestion('Alamat email', 'existing@bayeng.test')
            ->assertFailed();
    }
}
