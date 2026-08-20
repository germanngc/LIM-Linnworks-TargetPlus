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
            'Source'                => (string) config('services.linnworks.order_source', 'TARGET_PLUS'),
            'SubSource'             => (string) config('services.linnworks.order_subsource', 'Target Plus'),
            'ReferenceNumber'       => $order->target_order_id,
            'ExternalReference'     => $order->target_order_number,
            'PaymentStatus'         => $this->mapPaymentStatus($order->lwPaymentStatus()),
            'OrderStatusType'       => 'Unshipped',
            'Currency'              => $payload['currency'] ?? 'USD',
            'ReceivedDate'          => $this->toUtcString($payload['order_date'] ?? null),
            'DispatchBy'            => $this->toUtcString($payload['requested_shipment_date'] ?? null),
            'PostalServiceCost'     => 0,
            'PostalServiceTaxRate'  => 0,
            'UseChannelTax'         => false,
            'MatchPostalServiceTag' => $payload['level_of_service'] ?? '',
            'MatchPaymentMethodTag' => 'Target Plus',
            'ChannelBuyerName'      => trim(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? '')),
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
            'MatchCountryCode' => $a['country_code'] ?? 'US',
            'FullName'     => trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')),
            'Company'      => '',
            'Address1'     => $a['address1'] ?? '',
            'Address2'     => $a['address2'] ?? '',
            'Address3'     => '',
            'Town'         => $a['city'] ?? '',
            'Region'       => $a['state'] ?? '',
            'PostCode'     => $a['postal_code'] ?? '',
            'Country'      => $a['country_code'] ?? 'US',
            'PhoneNumber'  => $phone,
            'EmailAddress' => '',
        ];
    }

    private function mapOrderLines(array $lines): array
    {
        $mapped = [];

        foreach ($lines as $line) {
            $mapped[] = [
                'ItemNumber'         => (string) ($line['order_line_number'] ?? ''),
                'ChannelSKU'         => (string) ($line['tcin'] ?? ''),
                'ItemTitle'          => (string) ($line['tcin'] ?? ''),
                'Qty'                => (int) ($line['quantity'] ?? 1),
                'PricePerUnit'       => (float) ($line['unit_price'] ?? 0),
                'TaxRate'            => 0,
                'LineDiscount'       => 0,
                'TaxCostInclusive'   => false,
                'UseChannelTax'      => false,
                'IsService'          => false,
                'Options'            => [],
                'Taxes'              => [],
            ];
        }

        return $mapped;
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
            return now()->utc()->toIso8601String();
        }

        return Carbon::parse($iso)->utc()->toIso8601String();
    }

    private function mapPaymentStatus(string $status): string
    {
        return strtoupper($status) === 'CANCELLED' ? 'Cancelled' : 'Paid';
    }
}
