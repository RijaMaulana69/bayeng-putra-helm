<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Order extends Model
{
    use HasFactory;

    /**
     * Nama tabel database yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'orders';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Kolom status, status_pembayaran, verifikasi, dan timestamp terminal
     * ditutup dari mass assignment publik untuk proteksi keamanan transaksi.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nomor_pesanan',
        'idempotency_key',
        'user_id',
        'metode_pembayaran',
        'total_produk',
        'total_harga',
        'diskon',
        'total_akhir',
        'nominal_kewajiban',
        'catatan',
        'expires_at',
    ];

    /**
     * Nilai default atribut model.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => OrderStatus::DIBUAT->value,
        'status_pembayaran' => PaymentStatus::BELUM_DIBAYAR->value,
        'total_produk' => 0,
        'total_harga' => 0,
        'diskon' => 0,
        'total_akhir' => 0,
        'nominal_kewajiban' => 0,
        'grace_period_used' => false,
    ];

    /**
     * Mendapatkan atribut yang harus di-cast ke tipe data tertentu.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'idempotency_key' => 'string',
            'user_id' => 'integer',
            'status' => OrderStatus::class,
            'status_pembayaran' => PaymentStatus::class,
            'metode_pembayaran' => PaymentMethod::class,
            'total_produk' => 'integer',
            'total_harga' => 'integer',
            'diskon' => 'integer',
            'total_akhir' => 'integer',
            'nominal_kewajiban' => 'integer',
            'grace_period_used' => 'boolean',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relasi ke akun pembeli pembuat pesanan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relasi ke seluruh rincian item pesanan.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /**
     * Relasi ke seluruh riwayat pembayaran terkait pesanan.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    /**
     * Relasi ke seluruh catatan audit status pesanan.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(OrderAuditLog::class, 'order_id');
    }

    /**
     * Apakah batas waktu pembayaran/pengambilan pesanan telah terlewati.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return Carbon::now('UTC')->greaterThanOrEqualTo($this->expires_at);
    }

    /**
     * Apakah pesanan masih memenuhi syarat untuk pembayaran online.
     */
    public function canBePaidOnline(): bool
    {
        return $this->metode_pembayaran->requiresProofUpload()
            && $this->status === OrderStatus::MENUNGGU_PEMBAYARAN
            && $this->status_pembayaran === PaymentStatus::BELUM_DIBAYAR
            && ! $this->isExpired();
    }

    /**
     * Apakah pesanan memenuhi syarat untuk pelunasan tunai kasir di toko.
     */
    public function canBePaidAtCashier(): bool
    {
        return $this->metode_pembayaran === PaymentMethod::BAYAR_DI_TOKO
            && $this->status === OrderStatus::SIAP_DIAMBIL
            && $this->status_pembayaran === PaymentStatus::BELUM_DIBAYAR
            && ! $this->isExpired();
    }
}
