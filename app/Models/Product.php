<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nama tabel database yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'products';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'brand_id',
        'category_id',
        'nama',
        'slug',
        'deskripsi',
        'spesifikasi',
        'is_active',
    ];

    /**
     * Mendapatkan atribut yang harus di-cast ke tipe data tertentu.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'brand_id' => 'integer',
            'category_id' => 'integer',
            'spesifikasi' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relasi ke entitas merek induk.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Relasi ke entitas kategori/jenis induk.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Relasi ke seluruh varian fisik (warna & ukuran) dari produk ini.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class, 'product_id');
    }

    /**
     * Relasi ke seluruh galeri foto milik produk ini.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id');
    }

    /**
     * Alias relasi ke galeri foto produk.
     */
    public function productImages(): HasMany
    {
        return $this->images();
    }
}
