<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TargetProduct extends Model
{
    protected $fillable = [
        'tenant_id',
        'tcin',
        'external_id',
        'sku',
        'title',
        'status',
        'available_quantity',
        'listing_active',
        'raw_payload',
        'inventory_synced_at',
        'linnworks_stock_item_id',
        'linnworks_channel_mapping_id',
        'linnworks_synced_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'listing_active' => 'boolean',
        'inventory_synced_at' => 'datetime',
        'linnworks_synced_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
