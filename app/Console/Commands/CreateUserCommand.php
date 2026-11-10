<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create
                            {--name= : Nama lengkap pengguna}
                            {--email= : Alamat email pengguna}
                            {--role= : Role pengguna (owner, karyawan, pembeli)}
                            {--password= : Password pengguna (opsional, disarankan via input interaktif)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membuat akun pengguna (Owner, Karyawan, atau Pembeli) secara aman melalui konsol';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== Pembuatan Akun Pengguna Bayeng Putra Helm ===');

        // 1. Dapatkan & validasi Nama
        $name = $this->option('name') ?: $this->ask('Nama pengguna');
        $nameValidator = Validator::make(['name' => $name], [
            'name' => ['required', 'string', 'max:255'],
        ]);
        if ($nameValidator->fails()) {
            $this->error($nameValidator->errors()->first('name'));

            return self::FAILURE;
        }

        // 2. Dapatkan & validasi Email
        $email = $this->option('email') ?: $this->ask('Alamat email');
        $emailValidator = Validator::make(['email' => $email], [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ]);
        if ($emailValidator->fails()) {
            $this->error($emailValidator->errors()->first('email'));

            return self::FAILURE;
        }

        // 3. Dapatkan & validasi Role
        $role = $this->option('role');
        $allowedRoles = Role::values();

        if (! $role || ! in_array($role, $allowedRoles, true)) {
            $role = $this->choice(
                'Pilih role pengguna',
                $allowedRoles,
                Role::PEMBELI->value
            );
        }

        // 4. Dapatkan & validasi Password
        $password = $this->option('password') ?: $this->secret('Masukkan password (minimal 8 karakter)');
        $passwordValidator = Validator::make(['password' => $password], [
            'password' => ['required', 'string', Password::min(8)],
        ]);
        if ($passwordValidator->fails()) {
            $this->error($passwordValidator->errors()->first('password'));

            return self::FAILURE;
        }

        // 5. Konfirmasi sebelum menyimpan
        $this->table(
            ['Atribut', 'Nilai'],
            [
                ['Nama', $name],
                ['Email', $email],
                ['Role', $role],
            ]
        );

        if (! $this->confirm('Apakah data di atas sudah benar dan Anda ingin melanjutkan pembuatan akun?', true)) {
            $this->warn('Pembuatan akun dibatalkan.');

            return self::INVALID;
        }

        // 6. Simpan pengguna secara eksplisit
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->role = Role::from($role);
        $user->email_verified_at = now();
        $user->save();

        $this->info("Pengguna [{$user->name}] dengan role [{$user->role->label()}] berhasil dibuat.");

        return self::SUCCESS;
    }
}
