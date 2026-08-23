<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockVendorStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_mutations_are_visible_to_later_reads(): void
    {
        $base = '/mock-vendor/accounts/stateful-account';
        $created = $this->postJson("{$base}/products", [
            'sku' => 'STATE-1', 'title' => 'Stateful Product', 'available_quantity' => 5, 'price' => 10,
        ])->assertCreated();
        $vendor_reference = $created->json('vendor_reference');

        $this->putJson("{$base}/products/{$vendor_reference}/inventory", ['available_quantity' => 17])->assertOk();
        $this->putJson("{$base}/products/{$vendor_reference}/price", ['price' => 29.95])->assertOk();

        $this->getJson("{$base}/products?per_page=100")->assertOk()
            ->assertJsonFragment(['sku' => 'STATE-1', 'available_quantity' => 17, 'price' => 29.95]);
        $this->getJson("{$base}/products/{$vendor_reference}/inventory")->assertOk()
            ->assertJsonPath('available_quantity', 17);

        $this->deleteJson("{$base}/products/{$vendor_reference}")->assertOk();
        $this->getJson("{$base}/products?per_page=100")->assertJsonFragment(['sku' => 'STATE-1', 'status' => 'INACTIVE']);
    }

    public function test_order_mutations_are_visible_to_later_reads(): void
    {
        $base = '/mock-vendor/accounts/order-state';
        $this->putJson("{$base}/orders/MOCK-ORDER-10001/cancel", ['Items' => []])->assertOk();
        $this->getJson("{$base}/orders")->assertJsonFragment(['id' => 'MOCK-ORDER-10001', 'status' => 'CANCELED']);
    }
}
