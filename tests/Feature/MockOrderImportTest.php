<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TpOrder;
use App\Services\MockTargetPlusStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockOrderImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_orders_can_be_pulled_without_self_http_server(): void
    {
        $tenant = Tenant::create([
            'linnworks_user_id' => 'mock-order-user',
            'linnworks_email' => 'mock-order@example.com',
            'linnworks_application_token' => 'app-token',
            'linnworks_token' => 'session-token',
            'linnworks_server' => 'https://api.linnworks.net',
            'authorization_token' => Tenant::generateAuthorizationToken(),
            'target_api_key' => 'api-key',
            'target_seller_id' => 'seller-mock-order',
            'target_seller_token' => 'seller-token',
            'target_environment' => 'stage',
            'active' => true,
        ]);

        config(['services.target_plus.base_url' => 'http://127.0.0.1:8000/mock-target-plus']);

        app(MockTargetPlusStore::class)->addOrder($tenant->target_seller_id, [
            'id' => 'TP-MOCK-IMPORT-1',
            'order_id' => 'TP-MOCK-IMPORT-1',
            'order_number' => 'TP-MOCK-IMPORT-1',
            'order_date' => now()->utc()->toIso8601String(),
            'last_modified' => now()->utc()->toIso8601String(),
            'status' => 'NEW',
            'currency' => 'USD',
            'order_lines' => [[
                'order_line_number' => 1,
                'sku' => 'SKU-1001',
                'tcin' => 'SKU-1001',
                'quantity' => 1,
                'price' => 79.99,
                'status' => 'PENDING',
            ]],
        ]);

        $this->artisan('tp:pull-orders', ['tenant_id' => $tenant->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('tp_orders', [
            'tenant_id' => $tenant->id,
            'target_order_id' => 'TP-MOCK-IMPORT-1',
            'sync_status' => TpOrder::SYNC_PENDING,
        ]);

        $this->postJson('/api/Order/Orders', [
            'AuthorizationToken' => $tenant->authorization_token,
            'PageNumber' => 1,
            'UTCTimeFrom' => now()->subMinute()->utc()->toIso8601String(),
        ])->assertOk()
            ->assertJsonPath('Error', null)
            ->assertJsonPath('Orders.0.ReferenceNumber', 'TP-MOCK-IMPORT-1')
            ->assertJsonPath('Orders.0.OrderItems.0.SKU', 'SKU-1001');
    }
}
