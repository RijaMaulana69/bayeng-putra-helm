<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => Role::PEMBELI,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    /**
     * Periksa apakah pengguna memiliki role Owner.
     */
    public function isOwner(): bool
    {
        return $this->role === Role::OWNER;
    }

    /**
     * Periksa apakah pengguna memiliki role Karyawan.
     */
    public function isKaryawan(): bool
    {
        return $this->role === Role::KARYAWAN;
    }

    /**
     * Periksa apakah pengguna memiliki role Pembeli.
     */
    public function isPembeli(): bool
    {
        return $this->role === Role::PEMBELI;
    }

    /**
     * Periksa apakah pengguna memiliki salah satu role yang ditentukan.
     */
    public function hasRole(Role|string ...$roles): bool
    {
        $currentRoleValue = $this->role instanceof Role ? $this->role->value : (string) $this->role;

        foreach ($roles as $role) {
            $expectedRoleValue = $role instanceof Role ? $role->value : (string) $role;

            if ($currentRoleValue === $expectedRoleValue) {
                return true;
            }
        }

        return false;
    }
}
