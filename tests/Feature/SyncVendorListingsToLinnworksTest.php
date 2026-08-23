<?php

namespace Tests\Feature;

use App\Models\VendorProduct;
use App\Models\Tenant;
use App\Services\LinnworksInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncVendorListingsToLinnworksTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_linnworks_stock_item_and_channel_mapping(): void
    {
        $tenant = Tenant::create([
            'linnworks_user_id' => 'user-1',
            'linnworks_email' => 'test@example.com',
            'linnworks_application_token' => 'app-token',
            'linnworks_token' => 'linnworks-session-token',
            'linnworks_server' => 'https://eu-ext.linnworks.net',
            'authorization_token' => Tenant::generateAuthorizationToken(),
            'vendor_api_key' => 'vendor-api-key-123',
            'vendor_account_id' => 'account-123',
            'vendor_account_token' => 'vendor-account-token-123',
            'vendor_environment' => 'prod',
            'active' => true,
        ]);

        $product = VendorProduct::create([
            'tenant_id' => $tenant->id,
            'vendor_reference' => '12345678',
            'external_id' => 'EXT-12345678',
            'sku' => 'SKU-123',
            'title' => 'Vendor Product Title',
            'status' => 'ACTIVE',
            'available_quantity' => 12,
            'listing_active' => true,
            'raw_payload' => ['title' => 'Vendor Product Title'],
        ]);

        Http::fake([
            'https://eu-ext.linnworks.net/api/Inventory/GetInventoryItem*' => Http::sequence()
                ->push([], 200)
                ->push([
                    'ItemNumber' => 'SKU-123',
                    'ItemTitle' => 'Vendor Product Title',
                    'Quantity' => 12,
                    'StockItemId' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                ], 200),
            'https://eu-ext.linnworks.net/api/Inventory/AddInventoryItem' => Http::response(null, 204),
            'https://eu-ext.linnworks.net/api/Inventory/UpdateInventoryItemStockField' => Http::response(['ok' => true], 200),
            'https://eu-ext.linnworks.net/api/Inventory/CreateChannelMapping' => Http::response([
                'Results' => [[
                    'Result' => [
                        'Id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                    ],
                    'ResultStatus' => 'SUCCESSFUL',
                ]],
                'TotalResults' => 1,
                'ResultStatus' => 'SUCCESSFUL',
            ], 200),
        ]);

        $result = app(LinnworksInventoryService::class)->syncVendorProductListing($tenant, $product);

        $this->assertSame('created', $result['status']);
        $this->assertSame('SKU-123', $result['sku']);
        $this->assertSame('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', $result['channel_mapping_id']);

        $product->refresh();

        $this->assertSame('SKU-123', $product->sku);
        $this->assertSame('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', $product->linnworks_channel_mapping_id);
        $this->assertNotNull($product->linnworks_stock_item_id);
        $this->assertNotNull($product->linnworks_synced_at);
    }
}
