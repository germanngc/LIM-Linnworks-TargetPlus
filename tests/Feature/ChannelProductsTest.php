<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\VendorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_mappable_vendor_api_products_for_the_tenant(): void
    {
        $tenant = $this->tenant('tenant-1');
        $otherTenant = $this->tenant('tenant-2');

        VendorProduct::create([
            'tenant_id' => $tenant->id,
            'vendor_reference' => '12345678901',
            'external_id' => 'EXT-001',
            'sku' => 'SKU-1001',
            'title' => 'Mapped Product',
            'status' => 'ACTIVE',
            'available_quantity' => 12,
            'price' => 19.95,
            'listing_active' => true,
            'raw_payload' => ['price' => 19.95],
        ]);

        VendorProduct::create([
            'tenant_id' => $otherTenant->id,
            'vendor_reference' => '99999999999',
            'sku' => 'OTHER-SKU',
            'title' => 'Other Tenant Product',
        ]);

        $this->postJson('/api/Product/Products', [
            'AuthorizationToken' => $tenant->authorization_token,
            'PageNumber' => 1,
        ])->assertOk()
            ->assertJsonPath('Error', null)
            ->assertJsonPath('HasMorePages', false)
            ->assertJsonCount(1, 'Products')
            ->assertJsonPath('Products.0.SKU', 'SKU-1001')
            ->assertJsonPath('Products.0.Title', 'Mapped Product')
            ->assertJsonPath('Products.0.Quantity', 12)
            ->assertJsonPath('Products.0.Price', 19.95)
            ->assertJsonPath('Products.0.Reference', '12345678901');
    }

    private function tenant(string $userId): Tenant
    {
        return Tenant::create([
            'linnworks_user_id' => $userId,
            'linnworks_email' => $userId . '@example.com',
            'linnworks_application_token' => 'app-token',
            'linnworks_token' => 'session-token',
            'linnworks_server' => 'https://api.linnworks.net',
            'authorization_token' => Tenant::generateAuthorizationToken(),
            'vendor_api_key' => 'api-key',
            'vendor_account_id' => 'account-id',
            'vendor_account_token' => 'account-token',
            'vendor_environment' => 'stage',
            'active' => true,
        ]);
    }
}
