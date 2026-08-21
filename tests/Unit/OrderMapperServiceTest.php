<?php

namespace Tests\Unit;

use App\Models\TpOrder;
use App\Services\OrderMapperService;
use Tests\TestCase;

class OrderMapperServiceTest extends TestCase
{
    public function test_it_maps_target_order_lines_to_required_linnworks_fields(): void
    {
        $order = new TpOrder([
            'target_order_id' => 'TP-10001',
            'target_order_number' => 'TP-10001',
            'target_status' => 'NEW',
            'target_payload' => [
                'order_date' => '2026-08-20T22:00:00Z',
                'currency' => 'USD',
                'order_lines' => [[
                    'order_line_number' => 1,
                    'sku' => 'SKU-1001',
                    'tcin' => '12345678901',
                    'quantity' => 2,
                    'price' => 79.99,
                ]],
            ],
            'target_address' => [
                'address' => [
                    'first_name' => 'Jane',
                    'last_name' => 'Doe',
                    'country' => 'US',
                ],
            ],
        ]);

        $mapped = app(OrderMapperService::class)->toLinworks($order);

        $this->assertSame('PAID', $mapped['PaymentStatus']);
        $this->assertSame('Jane Doe', $mapped['DeliveryAddress']['FullName']);
        $this->assertSame('US', $mapped['DeliveryAddress']['CountryCode']);
        $this->assertSame('1', $mapped['OrderItems'][0]['OrderLineNumber']);
        $this->assertSame('SKU-1001', $mapped['OrderItems'][0]['SKU']);
        $this->assertSame(79.99, $mapped['OrderItems'][0]['PricePerUnit']);
        $this->assertArrayNotHasKey('ItemNumber', $mapped['OrderItems'][0]);
        $this->assertArrayNotHasKey('ChannelSKU', $mapped['OrderItems'][0]);
    }
}
