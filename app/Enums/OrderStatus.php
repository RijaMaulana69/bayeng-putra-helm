<?php

namespace App\Enums;

enum OrderStatus: string
{
    case DIBUAT = 'dibuat';
    case MENUNGGU_PEMBAYARAN = 'menunggu_pembayaran';
    case MENUNGGU_VERIFIKASI = 'menunggu_verifikasi';
    case DIKONFIRMASI = 'dikonfirmasi';
    case DISIAPKAN = 'disiapkan';
    case SIAP_DIAMBIL = 'siap_diambil';
    case SELESAI = 'selesai';
    case BATAL = 'batal';
    case KEDALUWARSA = 'kedaluwarsa';

    /**
     * Dapatkan label ramah pengguna dalam Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::DIBUAT => 'Dibuat',
            self::MENUNGGU_PEMBAYARAN => 'Menunggu Pembayaran',
            self::MENUNGGU_VERIFIKASI => 'Menunggu Verifikasi',
            self::DIKONFIRMASI => 'Dikonfirmasi',
            self::DISIAPKAN => 'Sedang Disiapkan',
            self::SIAP_DIAMBIL => 'Siap Diambil di Toko',
            self::SELESAI => 'Selesai',
            self::BATAL => 'Dibatalkan',
            self::KEDALUWARSA => 'Kedaluwarsa',
        };
    }

    /**
     * Apakah pesanan berada dalam status akhir (terminal).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::SELESAI, self::BATAL, self::KEDALUWARSA], true);
    }

    /**
     * Apakah pesanan masih aktif dan memegang alokasi reservasi stok.
     */
    public function holdsReservation(): bool
    {
        return in_array($this, [
            self::DIBUAT,
            self::MENUNGGU_PEMBAYARAN,
            self::MENUNGGU_VERIFIKASI,
            self::DIKONFIRMASI,
            self::DISIAPKAN,
            self::SIAP_DIAMBIL,
        ], true);
    }

    /**
     * Dapatkan seluruh nilai string dari enum OrderStatus.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
