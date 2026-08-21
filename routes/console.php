<?php

use Illuminate\Support\Facades\Schedule;

// Keep the local order cache fresh; Linnworks imports through /api/Order/Orders.
Schedule::command('tp:pull-orders')->everyFiveMinutes()->withoutOverlapping();

// Push Linnworks cancellations back to Target Plus every 15 minutes
Schedule::command('tp:push-cancellations')->everyFifteenMinutes()->withoutOverlapping();

// Keep Linnworks session tokens fresh
Schedule::command('lw:refresh-tokens')->everyThirtyMinutes()->withoutOverlapping();

// Target Plus catalog feeds Linnworks product mapping. Inventory quantities then
// flow from Linnworks to Target Plus through /api/Product/InventoryUpdate.
Schedule::command('tp:sync-catalog')->hourly()->withoutOverlapping();

// Refresh the channel-side quantity snapshot returned by /api/Product/Products.
Schedule::command('tp:sync-inventory')->everyThirtyMinutes()->withoutOverlapping();

// Bound operational history growth while retaining enough data for support.
Schedule::command('sync:prune-logs --days=90')->dailyAt('02:30')->withoutOverlapping();
