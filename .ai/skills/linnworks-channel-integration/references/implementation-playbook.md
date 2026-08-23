# Implementation Playbook

## 1. Start from the skeleton

Create a branch for the vendor. Keep the first commit as a generic baseline if possible.

Run `php artisan vendor:init` to capture the vendor docs, auth type, supported capabilities, and identifier mapping into `storage/app/vendor-channel/profile.json`.

## 2. Fill config

- `VENDOR_API_BASE_URL`
- auth fields in `Tenant`
- config fields in `ChannelIntegrationController::buildUserConfigResponse`
- setup UI labels

If the vendor needs OAuth, add OAuth routes before changing the Linnworks endpoints.

## 3. Adapt `VendorApiService`

Map every enabled capability to the vendor API. Keep method names generic where possible:

- `orders`
- `orderAddress`
- `confirmShipment`
- `cancelOrder`
- `refundOrder`
- `products`
- `productInventory`
- `updateProductInventory`
- `updateProductPrice`
- `createProduct`
- `updateProduct`
- `deleteProduct`

## 4. Adapt mapping

Order mapping must always provide:

- reference/order number;
- received/updated timestamps;
- customer and shipping address;
- at least one item with SKU and line number;
- totals/currency.

Listing mapping must always provide enough vendor fields to create or update a product/listing and enough Linnworks feed metadata for `CheckFeed`.

## 5. Preserve mocks

Update `MockVendorStore` and `MockVendorController` to match the vendor’s minimum payload shape. Keep mock routes deterministic and stateful.

## 6. Validate in this order

1. `php artisan test`
2. install/config screen opens in Linnworks;
3. `ConfigTest` succeeds;
4. create mock order and wait for Linnworks order download;
5. create listing and wait for `CheckFeed`;
6. update listing;
7. delete listing with marketplace close enabled;
8. inventory update;
9. despatch/process order;
10. cancellation/refund, if supported.

## 7. Logging checklist

Logs should show:

- endpoint name;
- tenant ID;
- correlation ID;
- requested count and result count;
- feed ID for listing actions;
- reference numbers/SKUs, not secrets;
- validation errors with enough context to fix mapping.
