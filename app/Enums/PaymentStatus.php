<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case BELUM_DIBAYAR = 'belum_dibayar';
    case MENUNGGU_KONFIRMASI = 'menunggu_konfirmasi';
    case SUDAH_DIBAYAR = 'sudah_dibayar';
    case KEDALUWARSA = 'kedaluwarsa';
    case BATAL = 'batal';
    case DIKEMBALIKAN = 'dikembalikan';

    /**
     * Dapatkan label ramah pengguna dalam Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::BELUM_DIBAYAR => 'Belum Dibayar',
            self::MENUNGGU_KONFIRMASI => 'Menunggu Verifikasi',
            self::SUDAH_DIBAYAR => 'Lunas',
            self::KEDALUWARSA => 'Kedaluwarsa',
            self::BATAL => 'Batal',
            self::DIKEMBALIKAN => 'Dikembalikan (Refund)',
        };
    }

    /**
     * Dapatkan seluruh nilai string dari enum PaymentStatus.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
