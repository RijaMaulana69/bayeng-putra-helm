<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    /**
     * Nama tabel database yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'product_images';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Kolom generated 'primary_flag' dikecualikan secara ketat.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'variant_id',
        'file_path',
        'alt_text',
        'urutan',
        'is_primary',
    ];

    /**
     * Mendapatkan atribut yang harus di-cast ke tipe data tertentu.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'urutan' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Relasi ke model produk pemilik gambar.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Relasi opsional ke varian spesifik produk.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class, 'variant_id');
    }
}
