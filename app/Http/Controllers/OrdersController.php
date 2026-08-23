<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TpOrder;
use App\Services\OrderMapperService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrdersController extends Controller
{
    private const PAGE_SIZE = 100;

    public function __construct(private OrderMapperService $mapper) {}

    /**
     * Linnworks calls this endpoint every ~10-15 min to pull new / updated orders.
     *
     * Request body: { AuthorizationToken, UTCTimeFrom, PageNumber }
     * Response:     { Error, HasMorePages, Orders[] }
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $from       = $request->input('UTCTimeFrom');
        $pageNumber = max(1, (int) $request->input('PageNumber', 1));
        $offset     = ($pageNumber - 1) * self::PAGE_SIZE;

        $query = TpOrder::where('tenant_id', $tenant->id);

        if ($from) {
            $fromDate = Carbon::parse($from)->utc();
            // Return orders created or status-changed since UTCTimeFrom
            $query->where(function ($q) use ($fromDate) {
                $q->where('target_order_date', '>=', $fromDate)
                  ->orWhere('target_updated_at', '>=', $fromDate);
            });
        }

        $total  = $query->count();
        $orders = $query->orderBy('target_order_date')->skip($offset)->take(self::PAGE_SIZE)->get();

        $mapped = $orders->map(fn (TpOrder $o) => $this->mapper->toLinworks($o))->values()->all();

        Log::info('ChannelIntegration Orders returned', [
            'tenant_id' => $tenant->id,
            'utc_time_from' => $from,
            'page_number' => $pageNumber,
            'returned' => count($mapped),
            'total' => $total,
            'reference_numbers' => $orders->pluck('target_order_id')->values()->all(),
        ]);

        return response()->json([
            'Error'        => null,
            'HasMorePages' => ($offset + self::PAGE_SIZE) < $total,
            'Orders'       => $mapped,
        ]);
    }
}
