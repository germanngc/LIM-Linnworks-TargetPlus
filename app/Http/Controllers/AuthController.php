<?php

namespace App\Http\Controllers;

use App\Models\TpProduct;
use App\Models\Tenant;
use App\Models\TpOrder;
use App\Services\LinnworksService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(private LinnworksService $linnworks) {}

    /**
     * Step 1: Linnworks opens this page with ?token= in the URL.
     * We validate the token with Linnworks and show the credentials form.
     */
    public function show(Request $request)
    {
        $token = trim((string) $request->query('token', ''));

        if (!$token) {
            return view('auth.error', ['message' => 'Missing Linnworks token. Please re-open from Linnworks.']);
        }

        $lwData = $this->linnworks->authorizeByApplication($token);

        if (!$lwData || empty($lwData['Token'])) {
            $error = $this->linnworks->getLastError();

            if ($error && str_contains(strtolower($error), 'token has expired')) {
                return view('auth.error', ['message' => 'Linnworks token expired. Re-open this app from Linnworks and complete setup immediately (token TTL is about 15 seconds).']);
            }

            return view('auth.error', ['message' => $error ?: 'Could not verify Linnworks token. Please try again.']);
        }

        $linnworksUserId = trim((string) ($lwData['Id'] ?? ''));
        $linnworksEmail = trim((string) ($lwData['Email'] ?? ''));

        if ($linnworksUserId === '' && $linnworksEmail !== '') {
            $linnworksUserId = 'lw-' . md5($linnworksEmail);
        }

        // Find or create the tenant stub so we preserve the authorization_token across page reloads
        $tenant = Tenant::firstOrCreate(
            ['linnworks_user_id' => $linnworksUserId],
            [
                'linnworks_email'             => $linnworksEmail,
                'linnworks_application_token' => $token,
                'linnworks_token'             => $lwData['Token'],
                'linnworks_server'            => $lwData['Server'] ?? 'https://api.linnworks.net',
                'authorization_token'         => Tenant::generateAuthorizationToken(),
                // Placeholder encrypted values — overwritten on form submit
                'target_api_key'              => '_pending_',
                'target_seller_id'            => '_pending_',
                'target_seller_token'         => '_pending_',
            ]
        );

        // Always refresh the session token
        $tenant->update([
            'linnworks_application_token' => $token,
            'linnworks_token'             => $lwData['Token'],
            'linnworks_server'            => $lwData['Server'] ?? $tenant->linnworks_server,
        ]);

        $configured = !str_starts_with($tenant->target_api_key, '_pending_');

        $showAuthorizationToken = (bool) config('services.linnworks.channel_integration');

        if ($configured) {
            return view('auth.success', array_merge([
                'tenant' => $tenant,
                'showAuthorizationToken' => $showAuthorizationToken,
            ], $this->buildDashboardData($tenant)));
        }

        return view('auth.setup', array_merge(
            compact('tenant', 'configured', 'showAuthorizationToken'),
            $this->buildDashboardData($tenant)
        ));
    }

    /**
     * Step 2: User submits their Target Plus credentials.
     */
    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'authorization_token' => 'required|uuid',
            'target_api_key'      => 'required|string|min:8',
            'target_seller_id'    => 'required|string|min:4',
            'target_seller_token' => 'required|string|min:8',
            'target_environment'  => 'required|in:prod,stage',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $tenant = Tenant::where('authorization_token', $request->authorization_token)->firstOrFail();

        $tenant->update([
            'target_api_key'      => $request->target_api_key,
            'target_seller_id'    => $request->target_seller_id,
            'target_seller_token' => $request->target_seller_token,
            'target_environment'  => $request->target_environment,
            'active'              => true,
        ]);

        return view('auth.success', array_merge([
            'tenant' => $tenant,
            'showAuthorizationToken' => (bool) config('services.linnworks.channel_integration'),
        ], $this->buildDashboardData($tenant)));
    }

    public function sync(Request $request, string $type)
    {
        $validator = Validator::make($request->all(), [
            'authorization_token' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return back()->with('sync_error', 'Could not run sync. Missing tenant authorization token.');
        }

        $tenant = Tenant::where('authorization_token', $request->authorization_token)
            ->where('active', true)
            ->first();

        if (!$tenant) {
            return back()->with('sync_error', 'Could not run sync. Tenant is missing or inactive.');
        }

        $commands = [
            'orders' => 'tp:pull-orders',
            'inventory' => 'tp:sync-inventory',
            'listings' => 'tp:sync-listings',
        ];

        $command = $commands[$type] ?? null;

        if (!$command) {
            return back()->with('sync_error', 'Could not run sync. Unsupported sync type.');
        }

        $current = Cache::get($this->syncStatusKey((int) $tenant->id, $type));

        if (($current['state'] ?? null) === 'running') {
            return back()->with('sync_error', ucfirst($type) . ' sync is already running. Please wait a moment and refresh.');
        }

        try {
            $this->putSyncState((int) $tenant->id, $type, 'running', ucfirst($type) . ' sync is running...');
            $tenantId = (int) $tenant->id;

            dispatch(function () use ($tenantId, $type) {
                $commands = [
                    'orders' => 'tp:pull-orders',
                    'inventory' => 'tp:sync-inventory',
                    'listings' => 'tp:sync-listings',
                ];

                $statusKey = "tenant:{$tenantId}:sync:{$type}:status";

                $command = $commands[$type] ?? null;

                if (!$command) {
                    Cache::put($statusKey, [
                        'state' => 'failed',
                        'message' => 'Unsupported sync type: ' . $type,
                        'updated_at' => now()->toIso8601String(),
                    ], now()->addHours(6));
                    return;
                }

                try {
                    $exitCode = Artisan::call($command, [
                        'tenant_id' => $tenantId,
                        '--no-interaction' => true,
                    ]);

                    if ($exitCode !== 0) {
                        $output = trim(Artisan::output());
                        $message = $output !== ''
                            ? $output
                            : 'Sync command returned non-zero exit code (' . $exitCode . ').';

                        Cache::put($statusKey, [
                            'state' => 'failed',
                            'message' => $message,
                            'updated_at' => now()->toIso8601String(),
                        ], now()->addHours(6));
                        return;
                    }

                    $message = match ($type) {
                        'orders' => 'Orders pulled from Target Plus and push attempted to Linnworks.',
                        'inventory' => 'Inventory sync completed.',
                        'listings' => 'Listings sync completed.',
                        default => ucfirst($type) . ' sync completed.',
                    };

                    Cache::put($statusKey, [
                        'state' => 'success',
                        'message' => $message,
                        'updated_at' => now()->toIso8601String(),
                    ], now()->addHours(6));
                } catch (\Throwable $e) {
                    Log::error('Queued sync failed', [
                        'tenant_id' => $tenantId,
                        'type' => $type,
                        'error' => $e->getMessage(),
                    ]);

                    Cache::put($statusKey, [
                        'state' => 'failed',
                        'message' => $e->getMessage(),
                        'updated_at' => now()->toIso8601String(),
                    ], now()->addHours(6));
                }
            })->onQueue('default');

            return back()->with('sync_status', ucfirst($type) . ' sync queued. Status will update automatically.');
        } catch (\Throwable $e) {
            $this->putSyncState((int) $tenant->id, $type, 'failed', 'Failed to queue sync: ' . $e->getMessage());

            return back()->with('sync_error', 'Sync failed: ' . $e->getMessage());
        }
    }

    public function syncStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'authorization_token' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid authorization token.'], 422);
        }

        $tenant = Tenant::where('authorization_token', $request->authorization_token)
            ->where('active', true)
            ->first();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found or inactive.'], 404);
        }

        $states = [];
        foreach (['orders', 'inventory', 'listings'] as $type) {
            $states[$type] = Cache::get(
                $this->syncStatusKey((int) $tenant->id, $type),
                ['state' => 'idle', 'message' => null, 'updated_at' => null]
            );
        }

        return response()->json(['states' => $states]);
    }

    private function syncStatusKey(int $tenantId, string $type): string
    {
        return "tenant:{$tenantId}:sync:{$type}:status";
    }

    private function putSyncState(int $tenantId, string $type, string $state, ?string $message = null): void
    {
        Cache::put($this->syncStatusKey($tenantId, $type), [
            'state' => $state,
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(6));
    }

    private function buildDashboardData(Tenant $tenant): array
    {
        $openOrders = TpOrder::where('tenant_id', $tenant->id)
            ->whereIn('target_status', TpOrder::ACTIVE_STATUSES)
            ->orderByDesc('target_order_date')
            ->limit(8)
            ->get();

        $products = TpProduct::where('tenant_id', $tenant->id)
            ->orderByDesc('linnworks_synced_at')
            ->orderByDesc('inventory_synced_at')
            ->limit(8)
            ->get();

        $linkedCount = TpProduct::where('tenant_id', $tenant->id)
            ->whereNotNull('linnworks_channel_mapping_id')
            ->count();

        $syncStates = [];
        foreach (['orders', 'inventory', 'listings'] as $type) {
            $syncStates[$type] = Cache::get(
                $this->syncStatusKey((int) $tenant->id, $type),
                ['state' => 'idle', 'message' => null, 'updated_at' => null]
            );
        }

        return [
            'openOrders' => $openOrders,
            'products' => $products,
            'linkedCount' => $linkedCount,
            'syncStates' => $syncStates,
        ];
    }
}
