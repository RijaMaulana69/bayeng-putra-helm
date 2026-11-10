<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RoleDashboardIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_owner_to_owner_dashboard(): void
    {
        $owner = User::factory()->create([
            'role' => Role::OWNER,
            'password' => Hash::make('password123'),
        ]);

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $owner->email)
            ->set('form.password', 'password123');

        $component->call('login');

        $component->assertRedirect(route('owner.dashboard', absolute: false));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_login_redirects_karyawan_to_karyawan_dashboard(): void
    {
        $karyawan = User::factory()->create([
            'role' => Role::KARYAWAN,
            'password' => Hash::make('password123'),
        ]);

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $karyawan->email)
            ->set('form.password', 'password123');

        $component->call('login');

        $component->assertRedirect(route('karyawan.dashboard', absolute: false));
        $this->assertAuthenticatedAs($karyawan);
    }

    public function test_login_redirects_pembeli_to_pembeli_dashboard(): void
    {
        $pembeli = User::factory()->create([
            'role' => Role::PEMBELI,
            'password' => Hash::make('password123'),
        ]);

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $pembeli->email)
            ->set('form.password', 'password123');

        $component->call('login');

        $component->assertRedirect(route('pembeli.dashboard', absolute: false));
        $this->assertAuthenticatedAs($pembeli);
    }

    public function test_central_dispatcher_redirects_authenticated_users_to_their_respective_dashboard(): void
    {
        $owner = User::factory()->create(['role' => Role::OWNER]);
        $karyawan = User::factory()->create(['role' => Role::KARYAWAN]);
        $pembeli = User::factory()->create(['role' => Role::PEMBELI]);

        // Tamu dialihkan ke login
        $this->get('/dashboard')->assertRedirect(route('login'));

        // Owner dialihkan ke owner.dashboard
        $this->actingAs($owner)->get('/dashboard')
            ->assertRedirect(route('owner.dashboard'));

        // Karyawan dialihkan ke karyawan.dashboard
        $this->actingAs($karyawan)->get('/dashboard')
            ->assertRedirect(route('karyawan.dashboard'));

        // Pembeli dialihkan ke pembeli.dashboard
        $this->actingAs($pembeli)->get('/dashboard')
            ->assertRedirect(route('pembeli.dashboard'));
    }

    public function test_unverified_user_is_redirected_to_verify_email_when_accessing_dashboard(): void
    {
        $unverifiedOwner = User::factory()->unverified()->create([
            'role' => Role::OWNER,
        ]);

        $this->actingAs($unverifiedOwner)
            ->get('/owner/dashboard')
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($unverifiedOwner)
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_intended_url_with_external_host_is_forgotten(): void
    {
        $pembeli = User::factory()->create([
            'role' => Role::PEMBELI,
            'password' => Hash::make('password123'),
        ]);

        session()->put('url.intended', 'https://attacker.test/phishing');

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $pembeli->email)
            ->set('form.password', 'password123');

        $component->call('login');

        // Intended eksternal dibatalkan, diarahkan aman ke dashboard pembeli
        $component->assertRedirect(route('pembeli.dashboard', absolute: false));
        $this->assertNull(session('url.intended'));
    }

    public function test_intended_url_with_userinfo_or_protocol_relative_is_forgotten(): void
    {
        $pembeli = User::factory()->create([
            'role' => Role::PEMBELI,
            'password' => Hash::make('password123'),
        ]);

        // Protocol-relative
        session()->put('url.intended', '//evil.com/hack');
        Volt::test('pages.auth.login')
            ->set('form.email', $pembeli->email)
            ->set('form.password', 'password123')
            ->call('login')
            ->assertRedirect(route('pembeli.dashboard', absolute: false));
        $this->assertNull(session('url.intended'));

        // Userinfo
        session()->put('url.intended', 'http://admin:secret@localhost:8000/profile');
        Volt::test('pages.auth.login')
            ->set('form.email', $pembeli->email)
            ->set('form.password', 'password123')
            ->call('login')
            ->assertRedirect(route('pembeli.dashboard', absolute: false));
        $this->assertNull(session('url.intended'));
    }

    public function test_intended_url_with_malformed_url_is_forgotten(): void
    {
        $pembeli = User::factory()->create([
            'role' => Role::PEMBELI,
            'password' => Hash::make('password123'),
        ]);

        // Backslash URL
        session()->put('url.intended', 'http://localhost/\evil.com');
        Volt::test('pages.auth.login')
            ->set('form.email', $pembeli->email)
            ->set('form.password', 'password123')
            ->call('login')
            ->assertRedirect(route('pembeli.dashboard', absolute: false));
        $this->assertNull(session('url.intended'));
    }

    public function test_intended_url_with_http_scheme_but_no_host_is_forgotten(): void
    {
        $pembeli = User::factory()->create([
            'role' => Role::PEMBELI,
            'password' => Hash::make('password123'),
        ]);

        session()->put('url.intended', 'http:///path-tanpa-host');
        Volt::test('pages.auth.login')
            ->set('form.email', $pembeli->email)
            ->set('form.password', 'password123')
            ->call('login')
            ->assertRedirect(route('pembeli.dashboard', absolute: false));
        $this->assertNull(session('url.intended'));
    }

    public function test_intended_url_across_roles_is_forgotten_and_redirects_to_own_dashboard(): void
    {
        $pembeli = User::factory()->create([
            'role' => Role::PEMBELI,
            'password' => Hash::make('password123'),
        ]);

        // Pembeli sebelumnya mencoba membuka /owner/dashboard
        session()->put('url.intended', url('/owner/dashboard'));

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $pembeli->email)
            ->set('form.password', 'password123');

        $component->call('login');

        // Intended lintas role dihapus, tidak terjadi 403, pembeli diarahkan ke dashboard miliknya
        $component->assertRedirect(route('pembeli.dashboard', absolute: false));
        $this->assertNull(session('url.intended'));
    }

    public function test_intended_url_for_allowed_route_is_preserved(): void
    {
        $pembeli = User::factory()->create([
            'role' => Role::PEMBELI,
            'password' => Hash::make('password123'),
        ]);

        // Rute /profile ada di allowlist pembeli
        session()->put('url.intended', url('/profile'));

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $pembeli->email)
            ->set('form.password', 'password123');

        $component->call('login');

        $component->assertRedirect(url('/profile'));
    }

    public function test_intended_url_with_unregistered_or_non_get_route_is_handled_gracefully(): void
    {
        $pembeli = User::factory()->create([
            'role' => Role::PEMBELI,
            'password' => Hash::make('password123'),
        ]);

        session()->put('url.intended', url('/rute-yang-tidak-terdaftar-404'));

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $pembeli->email)
            ->set('form.password', 'password123');

        $component->call('login');

        $component->assertRedirect(route('pembeli.dashboard', absolute: false));
        $this->assertNull(session('url.intended'));
    }

    public function test_cross_dashboard_access_returns_403_forbidden(): void
    {
        $owner = User::factory()->create(['role' => Role::OWNER]);
        $karyawan = User::factory()->create(['role' => Role::KARYAWAN]);
        $pembeli = User::factory()->create(['role' => Role::PEMBELI]);

        // Pembeli mengakses owner dan karyawan
        $this->actingAs($pembeli)->get('/owner/dashboard')->assertForbidden();
        $this->actingAs($pembeli)->get('/karyawan/dashboard')->assertForbidden();

        // Karyawan mengakses owner dan pembeli
        $this->actingAs($karyawan)->get('/owner/dashboard')->assertForbidden();
        $this->actingAs($karyawan)->get('/pembeli/dashboard')->assertForbidden();

        // Owner mengakses karyawan dan pembeli
        $this->actingAs($owner)->get('/karyawan/dashboard')->assertForbidden();
        $this->actingAs($owner)->get('/pembeli/dashboard')->assertForbidden();

        // Akses ke dashboard masing-masing sah (HTTP 200)
        $this->actingAs($owner)->get('/owner/dashboard')->assertOk();
        $this->actingAs($karyawan)->get('/karyawan/dashboard')->assertOk();
        $this->actingAs($pembeli)->get('/pembeli/dashboard')->assertOk();
    }

    public function test_user_model_fails_fast_with_value_error_on_corrupted_role_data(): void
    {
        // Masukkan data dengan role anomali langsung ke database
        DB::table('users')->insert([
            'name' => 'Data Rusak',
            'email' => 'corrupted@bayeng.test',
            'password' => Hash::make('password'),
            'role' => 'admin_ilegal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\ValueError::class);

        // Native enum casting melempar ValueError saat role diakses (integritas data fail-fast)
        $user = User::where('email', 'corrupted@bayeng.test')->first();
        $role = $user->role;
    }
}
