<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TpListingFeed extends Model
{
    protected $fillable = ['tenant_id', 'channel_feed_id', 'operation', 'request_hash', 'product_feeds'];

    protected $casts = ['product_feeds' => 'array'];
}
