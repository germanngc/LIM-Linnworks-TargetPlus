<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\LinnworksService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshLinnworksTokens extends Command
{
    protected $signature   = 'lw:refresh-tokens';
    protected $description = 'Refresh Linnworks session tokens for all active tenants.';

    public function __construct(private LinnworksService $linnworks)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenants = Tenant::where('active', true)->get();

        foreach ($tenants as $tenant) {
            $token = $this->linnworks->refreshToken($tenant);

            if ($token) {
                Log::info("Linnworks token refreshed for tenant {$tenant->id}");
            } else {
                Log::warning("Failed to refresh Linnworks token for tenant {$tenant->id} ({$tenant->linnworks_email})");
            }
        }

        return Command::SUCCESS;
    }
}
