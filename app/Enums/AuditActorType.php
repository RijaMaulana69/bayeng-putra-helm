<?php

namespace App\Enums;

enum AuditActorType: string
{
    case STAF = 'staf';
    case PEMBELI = 'pembeli';
    case SISTEM = 'sistem';

    /**
     * Dapatkan label ramah pengguna dalam Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::STAF => 'Staf / Kasir / Owner',
            self::PEMBELI => 'Pembeli',
            self::SISTEM => 'Sistem Otomatis (Scheduler)',
        };
    }

    /**
     * Dapatkan seluruh nilai string dari enum AuditActorType.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
