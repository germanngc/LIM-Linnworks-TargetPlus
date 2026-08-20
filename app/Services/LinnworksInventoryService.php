<?php

namespace App\Services;

use App\Models\TargetProduct;
use App\Models\Tenant;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinnworksInventoryService
{
    private const DEFAULT_CHANNEL_SOURCE = 'TARGET_PLUS';
    private const DEFAULT_CHANNEL_SUBSOURCE = 'DEFAULT';

    public function getInventoryItem(Tenant $tenant, ?string $stockItemId = null, ?string $sku = null): ?array
    {
        $query = array_filter([
            'stockItemId' => $stockItemId,
            'sKU' => $sku,
        ], static fn ($value) => $value !== null && $value !== '');

        return $this->request($tenant, 'GET', '/api/Inventory/GetInventoryItem', $query);
    }

    public function addInventoryItem(Tenant $tenant, array $inventoryItem): bool
    {
        return $this->request($tenant, 'POST', '/api/Inventory/AddInventoryItem', [
            'inventoryItem' => $inventoryItem,
        ]) !== null;
    }

    public function updateInventoryItemStockField(Tenant $tenant, string $stockItemId, string $fieldName, string $fieldValue): bool
    {
        return $this->request($tenant, 'POST', '/api/Inventory/UpdateInventoryItemStockField', [
            'inventoryItemId' => $stockItemId,
            'fieldName' => $fieldName,
            'fieldValue' => $fieldValue,
            'changeSource' => 'TargetPlusSync',
        ]) !== null;
    }

    public function createChannelMapping(Tenant $tenant, array $mappings): ?array
    {
        return $this->request($tenant, 'POST', '/api/Inventory/CreateChannelMapping', [
            'Mappings' => $mappings,
        ]);
    }

    public function updateChannelMapping(Tenant $tenant, array $mappings): ?array
    {
        return $this->request($tenant, 'POST', '/api/Inventory/UpdateChannelMapping', [
            'Mappings' => $mappings,
        ]);
    }

    public function syncTargetProductListing(
        Tenant $tenant,
        TargetProduct $product,
        ?string $source = null,
        ?string $subSource = null
    ): array {
        $source = $source ?: self::DEFAULT_CHANNEL_SOURCE;
        $subSource = $subSource ?: self::DEFAULT_CHANNEL_SUBSOURCE;
        $sku = $this->resolveSku($product);
        $title = trim((string) ($product->title ?: $sku));
        $quantity = max(0, (int) $product->available_quantity);
        $stockItem = $this->getInventoryItem($tenant, null, $sku);

        if (!$stockItem) {
            $created = $this->addInventoryItem($tenant, [
                'ItemNumber' => $sku,
                'ItemTitle' => $title,
                'MetaData' => $product->raw_payload ? json_encode($product->raw_payload) : null,
                'Quantity' => $quantity,
                'InOrder' => 0,
                'Due' => 0,
                'MinimumLevel' => null,
            ]);

            if (!$created) {
                return ['status' => 'failed', 'reason' => 'add_inventory_item_failed'];
            }

            $stockItem = $this->getInventoryItem($tenant, null, $sku);
        }

        $stockItemId = $stockItem['StockItemId'] ?? null;

        if (!$stockItemId) {
            return ['status' => 'failed', 'reason' => 'missing_stock_item_id'];
        }

        if (($stockItem['ItemTitle'] ?? null) !== $title) {
            $this->updateInventoryItemStockField($tenant, $stockItemId, 'Title', $title);
        }

        if ((int) ($stockItem['Quantity'] ?? 0) !== $quantity) {
            $this->updateInventoryItemStockField($tenant, $stockItemId, 'StockLevel', (string) $quantity);
        }

        $mappingId = $product->linnworks_channel_mapping_id;
        $mappingPayload = [
            'StockItemId' => $stockItemId,
            'ChannelSKU' => $sku,
            'Source' => $source,
            'SubSource' => $subSource,
            'ChannelReferenceId' => $product->tcin,
            'MaxListedQuantity' => $quantity,
            'EndWhenStock' => 0,
            'StockPercentage' => 100,
            'IgnoreSync' => false,
        ];

        $mappingResponse = $mappingId
            ? $this->updateChannelMapping($tenant, array_merge($mappingPayload, ['Id' => $mappingId]))
            : $this->createChannelMapping($tenant, $mappingPayload);

        $resolvedMappingId = $this->extractChannelMappingId($mappingResponse) ?: $mappingId;

        $product->update([
            'sku' => $sku,
            'linnworks_stock_item_id' => $stockItemId,
            'linnworks_channel_mapping_id' => $resolvedMappingId,
            'linnworks_synced_at' => now(),
        ]);

        return [
            'status' => $mappingId ? 'updated' : 'created',
            'sku' => $sku,
            'stock_item_id' => $stockItemId,
            'channel_mapping_id' => $resolvedMappingId,
        ];
    }

    private function resolveSku(TargetProduct $product): string
    {
        return trim((string) ($product->sku ?: $product->external_id ?: $product->tcin));
    }

    private function extractChannelMappingId(?array $response): ?string
    {
        foreach ($response['Results'] ?? [] as $result) {
            $mapping = $result['Result'] ?? [];

            if (!empty($mapping['Id'])) {
                return $mapping['Id'];
            }
        }

        return null;
    }

    private function request(Tenant $tenant, string $method, string $path, array $payload = []): ?array
    {
        try {
            $baseUrl = rtrim($tenant->linnworks_server, '/');
            $request = $this->httpClient($tenant);

            $response = match (strtoupper($method)) {
                'GET' => $request->get($baseUrl . $path, $payload),
                'POST' => $request->post($baseUrl . $path, $payload),
                'PUT' => $request->put($baseUrl . $path, $payload),
                default => throw new \InvalidArgumentException("Unsupported Linnworks method: {$method}"),
            };

            return $this->handleResponse($response, $method, $path, $tenant);
        } catch (\Throwable $e) {
            Log::error("Linnworks {$method} {$path} exception: " . $e->getMessage(), ['tenant' => $tenant->id]);

            return null;
        }
    }

    private function handleResponse(Response $response, string $method, string $path, Tenant $tenant): ?array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        Log::warning("Linnworks {$method} {$path} failed", [
            'tenant' => $tenant->id,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }

    private function httpClient(Tenant $tenant): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => $tenant->linnworks_token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->connectTimeout(8)
            ->timeout(20)
            ->retry(2, 200);
    }
}