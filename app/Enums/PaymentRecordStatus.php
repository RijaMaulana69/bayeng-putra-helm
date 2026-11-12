<?php

namespace App\Enums;

enum PaymentRecordStatus: string
{
    case MENUNGGU_VERIFIKASI = 'menunggu_verifikasi';
    case DITERIMA = 'diterima';
    case DITOLAK = 'ditolak';
    case MENUNGGU_PROSES = 'menunggu_proses';
    case DIPROSES = 'diproses';
    case BERHASIL = 'berhasil';
    case GAGAL = 'gagal';

    /**
     * Dapatkan label ramah pengguna dalam Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::MENUNGGU_VERIFIKASI => 'Menunggu Verifikasi Staf',
            self::DITERIMA => 'Pembayaran Diterima',
            self::DITOLAK => 'Pembayaran Ditolak',
            self::MENUNGGU_PROSES => 'Refund Menunggu Proses',
            self::DIPROSES => 'Refund Sedang Diproses',
            self::BERHASIL => 'Refund Berhasil',
            self::GAGAL => 'Refund Gagal',
        };
    }

    /**
     * Dapatkan seluruh nilai string dari enum PaymentRecordStatus.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
