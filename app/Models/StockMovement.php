<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    /**
     * Nama tabel database yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'stock_movements';

    /**
     * Nama kolom timestamp penyimpan waktu pembaruan.
     * Tabel stock_movements bersifat append-only sehingga tidak memiliki kolom updated_at.
     *
     * @var string|null
     */
    const UPDATED_AT = null;

    /**
     * Seluruh atribut ditutup dari mass assignment publik untuk menjamin integritas ledger.
     *
     * @var array<int, string>
     */
    protected $guarded = ['*'];

    /**
     * Mendapatkan atribut yang harus di-cast ke tipe data tertentu.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variant_id' => 'integer',
            'order_id' => 'integer',
            'order_item_id' => 'integer',
            'user_id' => 'integer',
            'perubahan_stok_fisik' => 'integer',
            'perubahan_stok_dipesan' => 'integer',
            'stok_fisik_sebelum' => 'integer',
            'stok_fisik_sesudah' => 'integer',
            'stok_dipesan_sebelum' => 'integer',
            'stok_dipesan_sesudah' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Model lifecycle boot: penegakan immutabilitas tingkat Eloquent (Defense in Depth).
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Security Violation: Model StockMovement bersifat immutable. Operasi UPDATE dilarang.');
        });

        static::deleting(function () {
            throw new \LogicException('Security Violation: Model StockMovement bersifat immutable. Operasi DELETE dilarang.');
        });
    }

    /**
     * Blokir method update() pada instance model.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Security Violation: Model StockMovement bersifat immutable. Operasi UPDATE dilarang.');
    }

    /**
     * Blokir method delete() pada instance model.
     */
    public function delete(): ?bool
    {
        throw new \LogicException('Security Violation: Model StockMovement bersifat immutable. Operasi DELETE dilarang.');
    }

    /**
     * Method otoritatif untuk mencatat baris baru pada buku besar (Ledger Entry).
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createLedgerEntry(array $attributes): self
    {
        return static::forceCreate($attributes);
    }

    /**
     * Relasi ke varian helm yang mengalami mutasi stok.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class, 'variant_id');
    }

    /**
     * Relasi opsional ke akun pengguna/staf yang mengeksekusi mutasi.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
