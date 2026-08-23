# Vendor Onboarding Questionnaire

Ask these in batches. Do not overwhelm the user if an API doc can answer the question.

## Vendor identity

- Vendor/marketplace name?
- Production API docs URL?
- Sandbox/staging docs URL, if different?
- Is there a sandbox account?

## Connection

- Auth type: API key, OAuth2, bearer token, HMAC/signature, mTLS, basic auth, or other?
- Required headers/query params?
- Token expiry/refresh rules?
- Rate limits and retry headers?
- Webhooks available, polling required, or both?

## Capabilities

- Orders: can we fetch new/updated orders? Which statuses should import?
- Catalog/products: can we fetch existing vendor products?
- Listings: can we create/update/deactivate marketplace listings?
- Inventory: can we read and update available quantity?
- Price: can we read and update price?
- Shipment/despatch: can we confirm fulfillment and tracking?
- Cancellation: can Linnworks cancel an order on the vendor?
- Refund/returns: can Linnworks submit refunds or only record them locally?

## Data mapping

- Primary product identifier in vendor API?
- SKU rules and uniqueness?
- Required product attributes for listing creation?
- Category/taxonomy requirements?
- Image requirements?
- Shipping method/carrier mapping?
- Currency and tax handling?
- Address/phone/email requirements?

## Operations

- Expected sync frequency?
- Maximum order/product page size?
- Backfill window?
- Failure notification channel?
- Data retention requirements?

