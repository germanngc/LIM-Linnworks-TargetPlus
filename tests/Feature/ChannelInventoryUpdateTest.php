<?php

namespace Tests\Feature;

use App\Models\VendorProduct;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannelInventoryUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_update_returns_per_sku_results_and_updates_mapped_products(): void
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

        $this->postJson('/api/Config/AddNewUser', [
            'Email' => 'linnworks@limmedia.io',
        ])->assertOk();

        $this->assertDatabaseHas('tenants', [
            'linnworks_email' => 'linnworks@limmedia.io',
        ]);

        $mapped = VendorProduct::create([
            'tenant_id' => $tenant->id,
            'vendor_reference' => '12345678901',
            'external_id' => 'EXT-001',
            'sku' => 'SKU-MAPPED',
            'title' => 'Mapped Product',
            'status' => 'ACTIVE',
            'available_quantity' => 5,
            'listing_active' => true,
            'raw_payload' => ['title' => 'Mapped Product'],
        ]);

        config(['services.vendor_api.base_url' => 'https://api.vendor.com/channel_api/v1']);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.vendor.com/channel_api/v1/accounts/account-123/products/12345678901/inventory' => Http::response([
                'success' => true,
                'vendor_reference' => '12345678901',
                'available_quantity' => 9,
            ], 200),
        ]);

        $response = $this->postJson('/api/Product/InventoryUpdate', [
            'AuthorizationToken' => $tenant->authorization_token,
            'Products' => [
                [
                    'SKU' => 'SKU-MAPPED',
                    'Quantity' => 9,
                ],
                [
                    'SKU' => 'SKU-UNKNOWN',
                    'Quantity' => 7,
                ],
                [
                    'Quantity' => 3,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('Error', null)
            ->assertJsonCount(3, 'Products')
            ->assertJsonPath('Products.0.SKU', 'SKU-MAPPED')
            ->assertJsonPath('Products.0.Error', null)
            ->assertJsonPath('Products.1.SKU', 'SKU-UNKNOWN')
            ->assertJsonPath('Products.1.Error', 'SKU is not mapped to a Vendor product')
            ->assertJsonPath('Products.2.SKU', '')
            ->assertJsonPath('Products.2.Error', 'Missing SKU');

        $mapped->refresh();
        $this->assertSame(9, $mapped->available_quantity);
        $this->assertNotNull($mapped->inventory_synced_at);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.vendor.com/channel_api/v1/accounts/account-123/products/12345678901/inventory'
                && $request['available_quantity'] === 9;
        });
    }
}
