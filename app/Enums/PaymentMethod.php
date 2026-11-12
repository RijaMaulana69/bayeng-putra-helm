<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case TRANSFER_BANK = 'transfer_bank';
    case EWALLET = 'ewallet';
    case BAYAR_DI_TOKO = 'bayar_di_toko';

    /**
     * Dapatkan label ramah pengguna dalam Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::TRANSFER_BANK => 'Transfer Bank Manual',
            self::EWALLET => 'E-Wallet (Simulasi)',
            self::BAYAR_DI_TOKO => 'Bayar Tunai di Toko (Pickup)',
        };
    }

    /**
     * Apakah metode pembayaran memerlukan unggah bukti transfer.
     */
    public function requiresProofUpload(): bool
    {
        return in_array($this, [self::TRANSFER_BANK, self::EWALLET], true);
    }

    /**
     * Batas waktu kedaluwarsa pesanan sejak checkout (dalam jam).
     */
    public function defaultTtlHours(): int
    {
        return match ($this) {
            self::TRANSFER_BANK, self::EWALLET => 2,
            self::BAYAR_DI_TOKO => 24,
        };
    }

    /**
     * Dapatkan seluruh nilai string dari enum PaymentMethod.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
