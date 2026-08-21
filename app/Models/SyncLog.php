<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['tenant_id', 'direction', 'event', 'target_order_id', 'success', 'message', 'logged_at'];

    protected $casts = ['success' => 'boolean', 'logged_at' => 'datetime'];
}
