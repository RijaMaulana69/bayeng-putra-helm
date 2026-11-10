<?php

namespace App\Enums;

enum Role: string
{
    case OWNER = 'owner';
    case KARYAWAN = 'karyawan';
    case PEMBELI = 'pembeli';

    /**
     * Dapatkan nama route dashboard resmi untuk role ini.
     */
    public function dashboardRoute(): string
    {
        return match ($this) {
            self::OWNER => 'owner.dashboard',
            self::KARYAWAN => 'karyawan.dashboard',
            self::PEMBELI => 'pembeli.dashboard',
        };
    }

    /**
     * Dapatkan label ramah pengguna dalam Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::KARYAWAN => 'Karyawan',
            self::PEMBELI => 'Pembeli',
        };
    }

    /**
     * Dapatkan seluruh nilai string dari enum Role.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
