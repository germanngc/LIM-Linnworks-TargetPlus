# Target Plus Core Features Deliverable for Linnworks

Date: 2026-09-20

Purpose: map Linnworks Orders Channel Core Features to currently discoverable Target Plus APIs and mark delivery risk clearly.

## Source documentation used

- Orders spec: https://plus.target.com/docs/spec/orders#overview
- Seller spec: https://plus.target.com/docs/spec/seller#overview
- Taxonomy spec: https://plus.target.com/docs/spec/taxonomy#overview

## Delivery matrix

| Linnworks Core Feature | Delivery Status | Target Plus API evidence | Notes for Linnworks |
|---|---|---|---|
| Listings: single API call to create, update, and manage listings | PARTIAL | POST /sellers/{seller_id}/products, PUT /sellers/{seller_id}/products/{product_id}, GET /sellers/{seller_id}/products_catalog, PUT /sellers/{seller_id}/products/{product_id}/statuses/{product_status_id}, PUT /sellers/{seller_id}/products/{product_id}-unlist | Product listing lifecycle is documented and can be delivered. |
| Listings: replicate across 5 countries and auto-translate local language | BLOCKED (doc gap) | No country replication endpoint identified in public specs. No translation endpoint identified in public specs. | This capability is not visible in discovered public docs and must be confirmed by Target/Linnworks. |
| Inventory: real-time stock synchronization | POSSIBLE | GET /sellers/{seller_id}/products/{product_id}/quantities, PUT /sellers/{seller_id}/products/{product_id}/quantities/bulk, PUT /sellers/{seller_id}/products/{product_id}/quantities/{distribution_center_id}, GET /sellers/{seller_id}/products/{product_id}/quantities/{distribution_center_id} | Quantity update APIs are documented. Real-time behavior depends on polling/event model and integration cadence. |
| Orders: automated order retrieval | POSSIBLE | GET /sellers/{seller_id}/orders, GET /sellers/{seller_id}/orders/{order_id}, GET /sellers/{seller_id}/order_addresses, GET /sellers/{seller_id}/order_addresses/{order_id} | Orders retrieval and address retrieval are documented. |
| Orders: shipping trigger / fulfillment update (drop ship remote flow) | POSSIBLE | POST /sellers/{seller_id}/orders/{order_id}/bulk_fulfillments_create, PUT /sellers/{seller_id}/orders/{order_id}/bulk_fulfillments_update, GET /sellers/{seller_id}/orders/{order_id}/fulfillments, GET /shipping_methods | Fulfillment and shipping method APIs are documented. |
| Orders: shipping label printing via API trigger | BLOCKED (doc gap) | No shipping label generation or label download endpoint identified in discovered public specs. | Requires confirmation of partner/private API or external carrier workflow. |
| Pricing: seller target payout (net proceeds) with fee+shipping final price calculation | BLOCKED (doc gap) | Pricing endpoints discovered: GET /sellers/{seller_id}/prices/{product_id}, PUT /sellers/{seller_id}/prices/{product_id}. No net-proceeds calculator endpoint identified. | Raw price set/get appears possible. Net proceeds calculator is not documented in discovered public specs. |
| Pricing automations: min/max threshold repricing | BLOCKED (doc gap) | No repricing automation endpoints identified in discovered public specs. | Needs explicit API contract from Target/Linnworks. |
| Promotions management: fetch eligible items, join marketplace deals, opt-out | BLOCKED (doc gap) | No promotions/deals enrollment APIs identified in discovered public specs. | Needs explicit API contract from Target/Linnworks. |

## Additional capability relevant to listing quality

Taxonomy and attribute endpoints are documented and can support product enrichment/mapping:

- GET /taxonomies
- GET /taxonomies/{taxonomy_id}
- GET /attributes
- GET /attributes/{attribute_id}
- GET /attribute_values
- GET /attribute_values/{attribute_value_id}
- GET /value_groups
- GET /value_groups/{value_group_id}
- GET /assignments
- GET /assignments/{assignment_id}
- GET /items
- GET /items/{tcin}

This supports category/attribute alignment, but not explicit country replication/translation behavior.

## Explicit risk flags to share with Linnworks

1. Country replication and auto-translation are not discoverable in currently visible public specs.
2. Shipping label printing APIs are not discoverable in currently visible public specs.
3. Net proceeds calculator API is not discoverable in currently visible public specs.
4. Repricing automation APIs are not discoverable in currently visible public specs.
5. Promotions/deals enrollment APIs are not discoverable in currently visible public specs.

## Recommended next action for contract closure

Ask Target/Linnworks for one of the following per blocked feature:

- Official endpoint path and OpenAPI section, or
- Written confirmation that feature is out-of-scope for seller APIs, plus required alternative flow.
