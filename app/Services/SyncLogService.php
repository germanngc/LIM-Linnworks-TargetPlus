<?php

namespace App\Services;

use App\Models\SyncLog;
use App\Models\Tenant;

class SyncLogService
{
    public function record(Tenant $tenant, string $direction, string $event, bool $success, ?string $message = null, ?string $vendorOrderId = null): void
    {
        SyncLog::create([
            'tenant_id' => $tenant->id,
            'direction' => $direction,
            'event' => $event,
            'vendor_order_id' => $vendorOrderId,
            'success' => $success,
            'message' => $message === null ? null : mb_substr($message, 0, 4000),
            'logged_at' => now(),
        ]);
    }
}
