<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorOrder extends Model
{
    protected $table = 'vendor_orders';

    protected $fillable = [
        'tenant_id',
        'vendor_order_id',
        'vendor_order_number',
        'vendor_status',
        'vendor_order_date',
        'vendor_updated_at',
        'vendor_payload',
        'vendor_address',
        'vendor_payload_checksum',
        'tracking_number',
        'carrier',
        'shipped_date',
        'lw_payment_status',
        'pushed_to_vendor_at',
        'cancel_pushed_to_vendor',
        'sync_status',
        'synced_to_linnworks_at',
        'sync_attempt',
        'last_sync_error',
        'refund_reference',
        'refunded_to_vendor_at',
        'despatch_checksum',
    ];

    protected $casts = [
        'vendor_payload' => 'array',
        'vendor_address' => 'array',
        'vendor_order_date' => 'datetime',
        'vendor_updated_at' => 'datetime',
        'shipped_date' => 'datetime',
        'pushed_to_vendor_at' => 'datetime',
        'synced_to_linnworks_at' => 'datetime',
        'cancel_pushed_to_vendor' => 'boolean',
        'refunded_to_vendor_at' => 'datetime',
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

    // Active statuses — orders still open on Vendor side
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
        return in_array($this->vendor_status, self::TERMINAL_STATUSES) ? 'CANCELLED' : 'PAID';
    }
}
