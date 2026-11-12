<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * Nama tabel database yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'order_items';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Seluruh data snapshot produk dan counter kuantitas diamankan dari mass-assignment publik liar.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'variant_id',
        'nama_produk',
        'sku',
        'warna',
        'ukuran',
        'harga_satuan',
        'kuantitas',
        'subtotal',
        'diskon_dialokasikan',
        'subtotal_bersih',
        'kuantitas_diserahkan',
        'kuantitas_dibatalkan',
        'kuantitas_kedaluwarsa',
    ];

    /**
     * Nilai default atribut model.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'diskon_dialokasikan' => 0,
        'subtotal_bersih' => 0,
        'kuantitas_diserahkan' => 0,
        'kuantitas_dibatalkan' => 0,
        'kuantitas_kedaluwarsa' => 0,
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
            'variant_id' => 'integer',
            'harga_satuan' => 'integer',
            'kuantitas' => 'integer',
            'subtotal' => 'integer',
            'diskon_dialokasikan' => 'integer',
            'subtotal_bersih' => 'integer',
            'kuantitas_diserahkan' => 'integer',
            'kuantitas_dibatalkan' => 'integer',
            'kuantitas_kedaluwarsa' => 'integer',
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
     * Relasi ke varian fisik katalog helm.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class, 'variant_id');
    }

    /**
     * Menghitung sisa kuantitas item yang masih memegang alokasi reservasi stok aktif.
     * Formula Invariant: Q_awal - (Q_diserahkan + Q_dibatalkan + Q_kedaluwarsa).
     */
    public function remainingReservedQuantity(): int
    {
        $allocated = $this->kuantitas_diserahkan + $this->kuantitas_dibatalkan + $this->kuantitas_kedaluwarsa;

        return max(0, $this->kuantitas - $allocated);
    }
}
