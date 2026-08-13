<?php

namespace App\Services;

use App\Models\TpOrder;
use Carbon\Carbon;

class OrderMapperService
{
    /**
     * Map a Target Plus order (+ its address) to the Linnworks Channel Integration Order schema.
     */
    public function toLinworks(TpOrder $order): array
    {
        $payload = $order->target_payload;
        $address = $order->target_address['shipping_address'] ?? [];

        return [
            'ReferenceNumber'       => $order->target_order_id,
            'ExternalReference'     => $order->target_order_number,
            'PaymentStatus'         => $order->lwPaymentStatus(),
            'Currency'              => $payload['currency'] ?? 'USD',
            'ReceivedDate'          => $this->toUtcString($payload['order_date'] ?? null),
            'DispatchBy'            => $this->toUtcString($payload['requested_shipment_date'] ?? null),
            'PostalServiceCost'     => 0,
            'PostalServiceTaxRate'  => 0,
            'UseChannelTax'         => false,
            'MatchPostalServiceTag' => $payload['level_of_service'] ?? '',
            'MatchPaymentMethodTag' => 'Target Plus',
            'ChannelBuyerName'      => trim(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? '')),
            'Site'                  => '',
            'BillingAddress'        => $this->mapAddress($address),
            'DeliveryAddress'       => $this->mapAddress($address),
            'OrderItems'            => $this->mapOrderLines($payload['order_lines'] ?? []),
            'ExtendedProperties'    => $this->mapExtendedProperties($payload),
            'Notes'                 => [],
        ];
    }

    private function mapAddress(array $a): array
    {
        $phone = $a['phone_numbers'][0]['number'] ?? '';

        return [
            'FullName'     => trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')),
            'Company'      => '',
            'Address1'     => $a['address1'] ?? '',
            'Address2'     => $a['address2'] ?? '',
            'Address3'     => '',
            'Town'         => $a['city'] ?? '',
            'Region'       => $a['state'] ?? '',
            'PostCode'     => $a['postal_code'] ?? '',
            'Country'      => '',
            'CountryCode'  => $a['country_code'] ?? 'US',
            'PhoneNumber'  => $phone,
            'EmailAddress' => '',
        ];
    }

    private function mapOrderLines(array $lines): array
    {
        $mapped = [];

        foreach ($lines as $line) {
            $lineStatus = $this->resolveLineStatus($line['order_line_statuses'] ?? []);

            $mapped[] = [
                'OrderLineNumber'    => $line['order_line_number'],
                'SKU'                => (string) ($line['tcin'] ?? ''),
                'ItemTitle'          => (string) ($line['tcin'] ?? ''),
                'Qty'                => (int) ($line['quantity'] ?? 1),
                'PricePerUnit'       => (float) ($line['unit_price'] ?? 0),
                'TaxRate'            => 0,
                'LinePercentDiscount' => 0,
                'TaxCostInclusive'   => false,
                'UseChannelTax'      => false,
                'IsService'          => false,
                'CancelStatus'       => $lineStatus,
                'Options'            => [],
            ];
        }

        return $mapped;
    }

    /**
     * Derive CANCELLED or NONE from the line's order_line_statuses array.
     */
    private function resolveLineStatus(array $statuses): string
    {
        foreach ($statuses as $s) {
            if (in_array($s['status'] ?? '', ['CANCELED', 'REFUNDED'])) {
                return 'CANCELLED';
            }
        }

        return 'NONE';
    }

    private function mapExtendedProperties(array $payload): array
    {
        $props = [];

        $infoFields = [
            'ship_advice_number'    => $payload['ship_advice_number'] ?? null,
            'ship_node_id'          => $payload['ship_node_id'] ?? null,
            'distribution_center_id' => $payload['distribution_center_id'] ?? null,
            'scac'                  => $payload['scac'] ?? null,
            'vmm_vendor_id'         => $payload['vmm_vendor_id'] ?? null,
        ];

        foreach ($infoFields as $name => $value) {
            if ($value !== null && $value !== '') {
                $props[] = ['Name' => $name, 'Value' => (string) $value, 'Type' => 'Info'];
            }
        }

        return $props;
    }

    private function toUtcString(?string $iso): string
    {
        if (!$iso) {
            return now()->format('Y-m-d H:i:sZ');
        }

        return Carbon::parse($iso)->utc()->format('Y-m-d H:i:sZ');
    }
}
