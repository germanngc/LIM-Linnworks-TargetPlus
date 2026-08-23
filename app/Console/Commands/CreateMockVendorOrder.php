<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\MockVendorStore;
use Illuminate\Console\Command;

class CreateMockVendorOrder extends Command
{
    protected $signature = 'vendor:mock-order
        {tenant_id : Tenant ID}
        {--sku=SKU-1001 : Order line SKU}
        {--quantity=1 : Order line quantity}
        {--price=79.99 : Order line price}
        {--status=NEW : Vendor order status}';

    protected $description = 'Create a fresh local mock Vendor order for Linnworks import testing.';

    public function handle(MockVendorStore $store): int
    {
        $tenant = Tenant::find((int) $this->argument('tenant_id'));

        if (!$tenant) {
            $this->error('Tenant not found.');
            return Command::FAILURE;
        }

        $sku = (string) $this->option('sku');
        $quantity = max(1, (int) $this->option('quantity'));
        $price = max(0, (float) $this->option('price'));
        $orderId = 'MOCK-ORDER-' . now()->utc()->format('YmdHis');

        $order = $store->addOrder($tenant->vendor_account_id, [
            'id' => $orderId,
            'order_id' => $orderId,
            'order_number' => $orderId,
            'order_date' => now()->utc()->toIso8601String(),
            'status' => (string) $this->option('status'),
            'currency' => 'USD',
            'total_amount' => $quantity * $price,
            'order_lines' => [[
                'order_line_number' => 1,
                'vendor_reference' => $sku,
                'sku' => $sku,
                'quantity' => $quantity,
                'price' => $price,
                'status' => 'PENDING',
            ]],
            'shipping_address' => [
                'first_name' => 'Mock',
                'last_name' => 'Buyer',
                'address1' => '123 Market Street',
                'address2' => '',
                'city' => 'Chicago',
                'state' => 'IL',
                'postal_code' => '60601',
                'country' => 'US',
            ],
        ]);

        $this->info("Created mock Vendor order {$order['id']} for tenant {$tenant->id} / account {$tenant->vendor_account_id}.");

        return Command::SUCCESS;
    }
}
