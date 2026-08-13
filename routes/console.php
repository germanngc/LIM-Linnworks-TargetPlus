<?php

use Illuminate\Support\Facades\Schedule;

// Pull new/updated orders from Target Plus into DB every 30 minutes
Schedule::command('tp:pull-orders')->everyThirtyMinutes()->withoutOverlapping();

// Push Linnworks cancellations back to Target Plus every 15 minutes
Schedule::command('tp:push-cancellations')->everyFifteenMinutes()->withoutOverlapping();

// Keep Linnworks session tokens fresh
Schedule::command('lw:refresh-tokens')->everyFifteenMinutes()->withoutOverlapping();

// Catalog sync for listing capabilities
Schedule::command('tp:sync-catalog')->daily()->withoutOverlapping();

// Inventory sync for stock availability and listing health
Schedule::command('tp:sync-inventory')->everyThirtyMinutes()->withoutOverlapping();

