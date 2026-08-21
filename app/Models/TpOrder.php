<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TpOrder extends Model
{
    protected $table = 'tp_orders';

    protected $fillable = [
        'tenant_id',
        'target_order_id',
        'target_order_number',
        'target_status',
        'target_order_date',
        'target_updated_at',
        'target_payload',
        'target_address',
        'target_payload_checksum',
        'tracking_number',
        'carrier',
        'shipped_date',
        'lw_payment_status',
        'pushed_to_target_at',
        'cancel_pushed_to_target',
        'sync_status',
        'synced_to_linnworks_at',
        'sync_attempt',
        'last_sync_error',
        'refund_reference',
        'refunded_to_target_at',
        'despatch_checksum',
    ];

    protected $casts = [
        'target_payload' => 'array',
        'target_address' => 'array',
        'target_order_date' => 'datetime',
        'target_updated_at' => 'datetime',
        'shipped_date' => 'datetime',
        'pushed_to_target_at' => 'datetime',
        'synced_to_linnworks_at' => 'datetime',
        'cancel_pushed_to_target' => 'boolean',
        'refunded_to_target_at' => 'datetime',
    ];

    public const SYNC_PENDING = 'pending';
    public const SYNC_SYNCED = 'synced';
    public const SYNC_FAILED = 'failed';
    public const SYNC_BLOCKED = 'blocked';

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    // Terminal statuses that Linnworks must see as CANCELLED
    public const TERMINAL_STATUSES = ['CANCELED', 'REFUNDED'];

    // Active statuses — orders still open on Target Plus side
    public const ACTIVE_STATUSES = [
        'NEW',
        'PROCESSING',
        'PENDING',
        'RELEASED_FOR_SHIPMENT',
        'ACKNOWLEDGED_BY_SELLER',
        'PARTIALLY_SHIPPED',
        'SHIPPED',
    ];

    public function lwPaymentStatus(): string
    {
        return in_array($this->target_status, self::TERMINAL_STATUSES) ? 'CANCELLED' : 'PAID';
    }
}
