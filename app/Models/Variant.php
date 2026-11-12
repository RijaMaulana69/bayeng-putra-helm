<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Variant extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nama tabel database yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'variants';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Kolom generated 'deleted_unique' dikecualikan secara ketat.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'sku',
        'warna',
        'ukuran',
        'harga',
        'stok_fisik',
        'stok_dipesan',
        'berat_gram',
        'is_active',
    ];

    /**
     * Mendapatkan atribut yang harus di-cast ke tipe data tertentu.
     * Seluruh nilai moneter dan kuantitas inventaris diperlakukan sebagai integer utuh.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'harga' => 'integer',
            'stok_fisik' => 'integer',
            'stok_dipesan' => 'integer',
            'berat_gram' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Model lifecycle boot: penanganan proteksi pemulihan (restore) varian.
     */
    protected static function booted(): void
    {
        static::restoring(function (Variant $variant) {
            $hasActiveConflict = static::where('product_id', $variant->product_id)
                ->where('warna', $variant->warna)
                ->where('ukuran', $variant->ukuran)
                ->whereNull('deleted_at')
                ->where('id', '!=', $variant->id)
                ->exists();

            if ($hasActiveConflict) {
                throw new \DomainException(
                    "Gagal memulihkan varian #{$variant->id}: kombinasi produk, warna '{$variant->warna}', dan ukuran '{$variant->ukuran}' sudah digunakan oleh varian aktif lain."
                );
            }
        });
    }

    /**
     * Accessor untuk menghitung stok tersedia riil (stok_fisik - stok_dipesan).
     */
    public function getStokTersediaAttribute(): int
    {
        return max(0, (int) $this->stok_fisik - (int) $this->stok_dipesan);
    }

    /**
     * Relasi ke model produk induk.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Relasi ke foto spesifik yang terikat pada varian ini.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }

    /**
     * Alias relasi ke foto varian produk.
     */
    public function productImages(): HasMany
    {
        return $this->images();
    }

    /**
     * Relasi ke seluruh riwayat buku besar (ledger) mutasi stok varian ini.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'variant_id');
    }
}
