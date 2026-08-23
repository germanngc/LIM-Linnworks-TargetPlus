# Linnworks Developer Portal Reference

Official portal: https://developer.linnworks.com/

Use this reference when creating or updating the Linnworks Developer Portal app for a channel generated from this skeleton.

## Developer Portal checklist

- Create or update a Linnworks app/module with `type: ChannelIntegration`.
- Use a stable public HTTPS base URL. Local ngrok URLs are acceptable for POC testing only.
- Keep `ChannelName` stable after users install the channel. Changing it can create a new channel identity in Linnworks.
- Set all callback endpoint URLs to the deployed app URL.
- Include permissions for the features enabled in the vendor capability matrix.
- Upload/use a generic or vendor-specific logo only after the repo has been intentionally branded.
- After changing URLs, reinstall or refresh the channel config in Linnworks if callbacks still hit an old URL.

## Permissions from the POC

The POC used these Linnworks permissions:

```json
[
  "GlobalPermissions.OrderBook.ViewOrderDetailsNode",
  "GlobalPermissions.OrderBook.ProcessedOrders.DownloadProcessedOrdersNode",
  "GlobalPermissions.Inventory.InventorySettings.LocationsNode",
  "GlobalPermissions.OrderBook.OpenOrders.MergeOrdersNode",
  "GlobalPermissions.Settings.ListingConfigurators.EditListingConfiguratorsNode",
  "GlobalPermissions.Settings.ListingConfigurators.GetListingConfiguratorsNode",
  "GlobalPermissions.Settings.ChannelIntegration.ChannelDetailsNode",
  "GlobalPermissions.Settings.ChannelIntegration.ChannelMappingNode",
  "GlobalPermissions.Inventory.Stock.StockReadNode"
]
```

Treat this as a starting point. Remove permissions for capabilities the vendor does not support, and add any additional permissions required by the final Linnworks app workflow.

## Endpoint parameters

Use `{APP_URL}` as the public HTTPS base URL, for example `https://integration.example.com`.

| Manifest parameter | Skeleton endpoint | Purpose |
| --- | --- | --- |
| `AddNewUserEndpoint` | `{APP_URL}/api/Config/AddNewUser` | Initial Linnworks tenant registration |
| `UserConfigEndpoint` | `{APP_URL}/api/Config/UserConfig` | Read channel config fields |
| `SaveConfigEndpoint` | `{APP_URL}/api/Config/SaveUserConfig` | Save channel config fields |
| `ShippingTagsEndpoint` | `{APP_URL}/api/Config/ShippingTags` | Available shipping tags |
| `PaymentTagsEndpoint` | `{APP_URL}/api/Config/PaymentTags` | Available payment tags |
| `ConfigDeletedEndpoint` | `{APP_URL}/api/Config/ConfigDeleted` | Mark tenant inactive when Linnworks notifies deletion |
| `ConfigTestEndpoint` | `{APP_URL}/api/Config/ConfigTest` | Validate credentials/config |
| `OrdersEndpoint` | `{APP_URL}/api/Order/Orders` | Linnworks order download polling |
| `DespatchEndpoint` | `{APP_URL}/api/Order/Despatch` | Shipment/despatch confirmation |
| `CancelEndpoint` | `{APP_URL}/api/Order/Cancel` | Order cancellation |
| `RefundEndpoint` | `{APP_URL}/api/Order/Refund` | Refund request |
| `PostSaleOptionsEndpoint` | `{APP_URL}/api/Order/PostSaleOptions` | Linnworks post-sale action discovery |
| `ProductsEndpoint` | `{APP_URL}/api/Product/Products` | Channel products for SKU mapping |
| `InventoryUpdateEndpoint` | `{APP_URL}/api/Product/InventoryUpdate` | Inventory updates from Linnworks |
| `PriceUpdateEndpoint` | `{APP_URL}/api/Product/PriceUpdate` | Price updates from Linnworks |
| `GetConfiguratorSettingsEndpoint` | `{APP_URL}/api/Listing/GetConfiguratorSettings` | Listing configurator UI settings |
| `GetCategoriesEndpoint` | `{APP_URL}/api/Listing/GetCategories` | Listing categories |
| `GetAttributesByCategoryEndpoint` | `{APP_URL}/api/Listing/GetAttributesByCategory` | Listing attributes |
| `GetVariationsByCategoryEndpoint` | `{APP_URL}/api/Listing/GetVariationsByCategory` | Listing variation definitions |
| `ListingUpdateEndpoint` | `{APP_URL}/api/Listing/ListingUpdate` | Listing create/update |
| `ListingDeleteEndpoint` | `{APP_URL}/api/Listing/ListingDelete` | Listing delete/deactivate |
| `CheckFeedEndpoint` | `{APP_URL}/api/Listing/CheckFeed` | Async listing feed polling |

## Capability toggles

The POC enabled:

```json
{
  "IsListingSupported": "True",
  "InventoryUpdateBatchSize": "1000",
  "DespatchUpdateBatchSize": "100",
  "PriceUpdateBatchSize": "1000",
  "ListingUpdateBatchSize": "50",
  "AllowRefundOnCancel": "True"
}
```

Adjust these per vendor. If a vendor does not support a feature, prefer disabling the capability in the manifest and documenting the limitation instead of accepting user actions that cannot be pushed to the vendor.

## Async behavior to remember

- Listing create/update/delete returns quickly with a `ChannelFeedId`.
- Linnworks polls `CheckFeed` later; this can take 5-20 minutes in the UI.
- `GetConfiguratorSettings` only proves the listing/config screen loaded.
- App deletion may not reliably trigger `ConfigDeletedEndpoint`; keep an admin stale-tenant cleanup process.

