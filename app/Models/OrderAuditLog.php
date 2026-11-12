<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditActorType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAuditLog extends Model
{
    use HasFactory;

    /**
     * Audit log bersifat append-only, tidak ada updated_at.
     */
    public const UPDATED_AT = null;

    protected $table = 'order_audit_logs';

    protected $fillable = [
        'order_id',
        'tipe_aktor',
        'staff_user_id',
        'buyer_user_id',
        'status_sebelum',
        'status_sesudah',
        'catatan',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'tipe_aktor' => AuditActorType::class,
        'staff_user_id' => 'integer',
        'buyer_user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Pesanan yang dicatat perubahannya.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Staf yang melakukan aksi (jika tipe_aktor = 'staf').
     */
    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    /**
     * Pembeli yang melakukan aksi (jika tipe_aktor = 'pembeli').
     */
    public function buyerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }
}
