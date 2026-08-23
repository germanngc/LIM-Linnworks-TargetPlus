# Linnworks Channel Integration Skill

Use this skill when creating or adapting a Linnworks External Channel integration for a new vendor.

## Goal

Turn the generic skeleton into a vendor-specific integration with the smallest safe set of changes:

- capture vendor credentials and environment choices;
- map vendor API resources into the generic services;
- enable only the capabilities the vendor actually supports;
- keep Linnworks-facing contracts stable;
- preserve the mock-first test harness.

## First questions to ask

Start with the questionnaire in `references/questionnaire.md`. Ask only the missing questions; infer answers from docs or code when possible.

Required minimum inputs:

- vendor name;
- vendor API docs URL or OpenAPI spec;
- authentication method;
- environments and base URLs;
- supported capabilities: orders, product/catalog, listings, inventory, shipment/despatch, cancellation, refund/returns;
- SKU and product identifier rules.

## Implementation rules

1. Keep Linnworks endpoint names and response shapes compatible with the Linnworks Channel Integration contract.
2. Rename public product branding only after the generic skeleton tests pass.
3. Keep database tables generic unless the repo is intentionally being branded for a single vendor.
4. Prefer capability flags over deleting code during discovery. Remove unused services only after the vendor scope is confirmed.
5. Keep in-app mocks for every enabled capability so Linnworks UI flows can be tested before real credentials exist.
6. Log every asynchronous handoff with a correlation ID: listing feed creation, `CheckFeed`, order returns, inventory updates, despatch, cancellation, refund, and unmatched routes.
7. Do not log tokens, API keys, customer payment data, or full AuthorizationToken values.

## Linnworks lessons baked into this skeleton

- Linnworks order downloads are polling-based. The app should pull vendor orders into `vendor_orders`; Linnworks later calls `/api/Order/Orders`.
- `Order Sync Date` maps to Linnworks’ `UTCTimeFrom`; orders older than the cursor will not be returned.
- Listing create/update/delete is asynchronous. Return a `ChannelFeedId`; Linnworks polls `CheckFeed` minutes later.
- Successful listing feeds should return product feed entries with `Messages: null`.
- Linnworks jobs can take 5-20 minutes before visible results or follow-up calls.
- `GetConfiguratorSettings` may only mean the UI loaded; it is not proof the user submitted a listing.
- Despatch/shipment calls can be retried. Use idempotency/checksums to ignore duplicate shipment confirmations.
- App deletion in Linnworks may not reliably call the integration cleanup endpoint; stale tenants need an admin cleanup path.

## Files to inspect first

- `routes/channel.php`
- `routes/mock.php`
- `app/Http/Controllers/ChannelIntegrationController.php`
- `app/Services/VendorApiService.php`
- `app/Services/MockVendorStore.php`
- `app/Services/OrderMapperService.php`
- `app/Models/Tenant.php`
- `config/services.php`
- `README.md`
- `INFRA.md`

## References

- `references/questionnaire.md`
- `references/capability-matrix.md`
- `references/implementation-playbook.md`
