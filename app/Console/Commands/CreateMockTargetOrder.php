<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\MockTargetPlusStore;
use Illuminate\Console\Command;

class CreateMockTargetOrder extends Command
{
    protected $signature = 'tp:mock-order
        {tenant_id : Tenant ID}
        {--sku=SKU-1001 : Order line SKU}
        {--quantity=1 : Order line quantity}
        {--price=79.99 : Order line price}
        {--status=NEW : Target Plus order status}';

    protected $description = 'Create a fresh local mock Target Plus order for Linnworks import testing.';

    public function handle(MockTargetPlusStore $store): int
    {
        $tenant = Tenant::find((int) $this->argument('tenant_id'));

        if (!$tenant) {
            $this->error('Tenant not found.');
            return Command::FAILURE;
        }

        $sku = (string) $this->option('sku');
        $quantity = max(1, (int) $this->option('quantity'));
        $price = max(0, (float) $this->option('price'));
        $orderId = 'TP-MOCK-' . now()->utc()->format('YmdHis');

        $order = $store->addOrder($tenant->target_seller_id, [
            'id' => $orderId,
            'order_id' => $orderId,
            'order_number' => $orderId,
            'order_date' => now()->utc()->toIso8601String(),
            'status' => (string) $this->option('status'),
            'currency' => 'USD',
            'total_amount' => $quantity * $price,
            'order_lines' => [[
                'order_line_number' => 1,
                'tcin' => $sku,
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

        $this->info("Created mock Target Plus order {$order['id']} for tenant {$tenant->id} / seller {$tenant->target_seller_id}.");

        return Command::SUCCESS;
    }
}
