<?php

namespace App\Enums;

enum PaymentRecordType: string
{
    case PEMBAYARAN = 'pembayaran';
    case REFUND = 'refund';

    /**
     * Dapatkan label ramah pengguna dalam Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::PEMBAYARAN => 'Pembayaran Masuk',
            self::REFUND => 'Pengembalian Dana (Refund)',
        };
    }

    /**
     * Dapatkan seluruh nilai string dari enum PaymentRecordType.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
