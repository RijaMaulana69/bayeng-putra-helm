<?php

namespace App\Models;

use App\Enums\EwalletProvider;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentRecordType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    /**
     * Nama tabel database yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'payments';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'nomor_transaksi',
        'tipe',
        'metode',
        'provider_ewallet',
        'nominal',
        'uang_diterima',
        'kembalian',
        'status',
        'bukti_transfer_path',
        'alasan',
        'idempotency_key',
        'request_hash',
        'staff_user_id',
        'verified_at',
    ];

    /**
     * Mendapatkan atribut yang harus di-cast ke tipe data tertentu.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'tipe' => PaymentRecordType::class,
            'metode' => PaymentMethod::class,
            'provider_ewallet' => EwalletProvider::class,
            'nominal' => 'integer',
            'uang_diterima' => 'integer',
            'kembalian' => 'integer',
            'status' => PaymentRecordStatus::class,
            'staff_user_id' => 'integer',
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relasi ke pesanan induk (Order).
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Relasi ke akun staf / kasir yang memverifikasi atau mencatat pembayaran.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    /**
     * Apakah pembayaran telah diverifikasi dan diterima sah.
     */
    public function isAccepted(): bool
    {
        return $this->status === PaymentRecordStatus::DITERIMA;
    }

    /**
     * Apakah pembayaran ditolak verifikasinya oleh staf.
     */
    public function isRejected(): bool
    {
        return $this->status === PaymentRecordStatus::DITOLAK;
    }

    /**
     * Apakah transaksi merupakan pembayaran tunai kasir toko.
     */
    public function isCashPayment(): bool
    {
        return $this->metode === PaymentMethod::BAYAR_DI_TOKO && $this->tipe === PaymentRecordType::PEMBAYARAN;
    }
}
