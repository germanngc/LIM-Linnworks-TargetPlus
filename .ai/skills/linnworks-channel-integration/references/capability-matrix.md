# Capability Matrix

Use this matrix when deciding what to enable for a vendor.

| Capability | Linnworks direction | Skeleton component | Keep when vendor supports | Disable/comment when missing |
| --- | --- | --- | --- | --- |
| Order sync | Vendor -> Linnworks | `vendor:pull-orders`, `/api/Order/Orders`, `vendor_orders` | Vendor has order list endpoint with updated cursor | Keep endpoint, return empty orders, document unsupported |
| Product/catalog sync | Vendor -> app/Linnworks | `vendor:sync-catalog`, `vendor_products` | Vendor has product/listing read API | Seed mock products only |
| Listing creation | Linnworks -> Vendor | `ListingUpdate`, `VendorApiService::createProduct` | Vendor supports create/list product | Return per-item validation error in feed |
| Listing control | Linnworks -> Vendor | `ListingUpdate`, `ListingDelete`, price/inventory methods | Vendor supports update/deactivate | Keep delete as local close only or unsupported feed message |
| Inventory sync | Both, often Linnworks -> Vendor | `InventoryUpdate`, `vendor:sync-inventory` | Vendor has inventory read/write APIs | Disable scheduled sync and return unsupported result |
| Shipment/despatch | Linnworks -> Vendor | Despatch controller/service | Vendor accepts tracking/fulfillment | Mark order locally and log unsupported vendor push |
| Cancellation | Linnworks -> Vendor | post-sale option handlers | Vendor accepts cancellations | Return unsupported post-sale option |
| Refund/returns | Linnworks -> Vendor | refund post-sale handler | Vendor accepts refunds | Return unsupported post-sale option |

Prefer explicit “unsupported” responses over silent success when a user action would imply vendor-side state changed.

