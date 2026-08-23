# Linnworks Channel Config Decisions

Date: 2026-08-20
Scope: TARGETPLUSCHANNEL settings in Linnworks "Edit Channel" screen.

## Goal

Define how we should operate the current channel integration for:

- Orders
- Inventory
- Listings

based on:

- the current app implementation
- observed install/config behavior
- Linnworks endpoint behavior

## What Linnworks Does (Short Version)

### Orders sync

- Linnworks pulls orders from `Order/Orders` every ~10-15 minutes.
- Request includes `UTCTimeFrom` and `PageNumber`.
- Integration should return paid/open orders only, paged, with idempotent `ReferenceNumber`.

Implication: Orders are pull-based from Linnworks to this app endpoint.

### Inventory sync

- Linnworks pushes changed inventory to `Product/InventoryUpdate`.
- It sends deltas, not full catalog each time.
- Quantity already includes Linnworks-side controls such as max listed, stock percentage, and end when.

Implication: Inventory is push-based from Linnworks to the channel integration.

### Price sync

- Linnworks pushes price deltas to `Product/PriceUpdate`.

Implication: Price is push-based and should be enabled only when endpoint is real.

### Listings mapping/download

- Linnworks fetches products from `Product/Products` for mapping.
- This is scheduled (roughly every 12h) and also user-triggerable via UI.
- `Quantity` and `Reference` are required in product rows.

Implication: Listings/mapping quality depends on a real `Products` feed.

## Current Implementation Status

- `Order/Orders`: implemented and paged via `OrdersController`.
- `Order/Despatch`: implemented and pushes shipment confirmation to vendor.
- `Order/Cancel`: stub (returns success, no channel-side cancellation action yet).
- `Order/Refund`: stub (returns success, no channel-side refund action yet).
- `Product/Products`: implemented from the local `vendor_products` cache with 100-item paging.
- `Product/InventoryUpdate`: implemented with per-SKU the vendor updates and results.
- `Product/PriceUpdate`: stub (ack only).
- Listing endpoints (`Listing/*`): discovery stubs.

## Decision Set (Now)

Use this for current safe operation:

### Account section

- Enabled: ON (only when tenant credentials are valid)
- Test button: should pass `ConfigTest`

Decision: keep enabled for order pull/despatch testing.

### Order section

- Despatch notes: OFF
- Cancellation notes: OFF
- Refund notes: OFF
- Order sync date: set to a recent timestamp for go-live

Decision:

- Keep notes off until cancel/refund behavior is fully implemented and verified.
- Set Order sync date to now minus 15-30 minutes during go-live to avoid large historical backfill.
- For controlled backfill, move date backward in small windows.

### Inventory section

- Update inventory: ON after products have been downloaded and mapped
- Max listed / Stock percentage / End when: configure according to the merchant's stock policy

Decision: enable after running `vendor:sync-catalog`, confirming `Product/Products` returns the expected SKUs, and completing Linnworks mapping.

### Inventory - Price Change section

- Price change: OFF

Decision: OFF for now because `PriceUpdate` is currently stubbed.

### Listings section

- Auto Link by SKU: ON only when the vendor and Linnworks SKUs are known to match
- Download Listings: use after `vendor:sync-catalog` populates `vendor_products`

Decision:

- Validate the returned SKU, title, quantity, and reference on a small product set first.
- Use manual mapping when SKU identity is uncertain.

## Why This Is The Right Interim Model

- Orders, despatch, product download, and inventory updates are implemented end-to-end.
- Price and listing-creation toggles can create false confidence because those endpoints still acknowledge without applying channel-side changes.
- This keeps behavior explicit and avoids hidden data drift.

## Next Decisions To Unlock Full Channel Mode

### Milestone A: Inventory verification

Required:

- Run `vendor:sync-catalog` and verify `vendor_products` is populated.
- Download/map products in Linnworks.
- Change stock for a mapped SKU and verify the the vendor update plus per-SKU response.

Then decision update:

- Turn `Update inventory` ON.

### Milestone B: Price ready

Required:

- Implement `Product/PriceUpdate` with per-SKU result array.

Then decision update:

- Turn `Price change` ON.

### Milestone C: Listing creation ready

Required:

- Validate Download Listings behavior in Linnworks UI using the implemented product feed.
- Implement the separate `Listing/*` creation/update endpoints if the vendor listing creation is required.

Then decision update:

- Enable and use listing download/mapping flows.
- Evaluate `Auto Link by SKU` ON after data quality checks.

## Proposed Operational Policy

Until price and listing creation are complete:

1. Use channel integration for order pull, despatch, product download, and mapped inventory updates.
2. Keep Price Change and listing-creation automation disabled.
3. Treat Linnworks as source of truth for stock and order state.
4. Revisit channel toggles only after each milestone test passes in a staging tenant.

## Quick Go-Live Checklist (Current State)

1. Install channel and confirm OAuth + AddNewUser + UserConfig + SaveUserConfig.
2. Set channel Enabled ON.
3. Set Order sync date to now minus 15-30 minutes.
4. Keep Inventory and Price Change toggles OFF.
5. Do a single test order through `Order/Orders` pull.
6. Despatch from Linnworks and verify the vendor shipment confirmation.
7. Monitor logs for endpoint and payload correctness.
