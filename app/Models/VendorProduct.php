<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorProduct extends Model
{
    protected $table = 'vendor_products';

    protected $fillable = [
        'tenant_id',
        'vendor_reference',
        'external_id',
        'sku',
        'title',
        'status',
        'available_quantity',
        'price',
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
        'price' => 'decimal:2',
        'inventory_synced_at' => 'datetime',
        'linnworks_synced_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
