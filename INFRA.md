# Infrastructure and Production Readiness

This document describes the infrastructure required to run the Linnworks channel integration, its data flow, scheduled processes, operational controls, and the remaining work before a production launch.

## Architecture

The integration is a multi-tenant Laravel application. It acts as the channel adapter between Linnworks and vendor.

```text
vendor API
      |
      | scheduled order/catalog pulls
      v
Laravel integration + MySQL
      ^                    |
      | Linnworks polling  | inventory, listing, shipment,
      |                    | cancellation and refund updates
      +--------------------+
             Linnworks
```

Orders are not fetched from the vendor on demand when Linnworks calls the channel. The application periodically pulls orders from the vendor into `vendor_orders`. Linnworks then polls `/api/Order/Orders`, which returns eligible orders from that local table.

Catalog products are stored in `vendor_products`. Linnworks uses `/api/Product/Products` to obtain the channel-side products needed for SKU mapping. Inventory changes flow in the opposite direction: Linnworks calls `/api/Product/InventoryUpdate`, and the integration forwards the update to vendor.

During development, vendor endpoints are mocked locally. The mock is stateful through the Laravel cache and can be reset with:

```http
DELETE /mock-vendor/accounts/{accountId}/_state
```

## Required Services

A production environment requires:

- A stable public HTTPS endpoint. Do not use an ephemeral tunnel URL for a persistent Linnworks installation.
- PHP 8.3 or later with the extensions required by Laravel and the MySQL PDO driver.
- A web server or application runtime for Laravel.
- MySQL or MariaDB with durable storage and automated backups.
- A continuously running Laravel scheduler.
- A continuously running queue worker when asynchronous jobs are enabled.
- A process supervisor such as systemd or Supervisor for scheduler and queue processes.
- Centralized application logs, error reporting, and alert delivery.

Redis is recommended in production for cache, locks, and queues, but the current application can use database-backed cache and queues. When `CACHE_STORE=database`, scheduler overlap locks require a working database connection; a database outage can therefore prevent scheduled commands from starting.

## Environment

At minimum, production must define:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://integration.example.com

DB_CONNECTION=mysql
DB_HOST=database-host
DB_PORT=3306
DB_DATABASE=lnw_channel
DB_USERNAME=lnw_channel_usr
DB_PASSWORD=use-a-secret-manager

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database

LINNWORKS_APP_ID=...
LINNWORKS_APP_SECRET=...
LINNWORKS_ORDER_SOURCE=VENDOR_CHANNEL

VENDOR_API_BASE_URL=https://vendor-api-base-url
```

vendor API keys, account tokens, Linnworks secrets, database credentials, and `APP_KEY` must be supplied through the deployment platform's secret manager. They must not be committed to source control or written to logs.

Each active tenant must have a valid vendor account ID, API key, account token, and environment. Inactive, deleted, or incompletely configured tenants are excluded from synchronization.

## Scheduled Work

The current schedule is defined in `routes/console.php`:

| Command | Interval | Purpose |
|---|---:|---|
| `vendor:pull-orders` | Every 5 minutes | Pull vendor orders into `vendor_orders` for Linnworks to poll |
| `vendor:push-cancellations` | Every 15 minutes | Forward Linnworks cancellation state to the vendor |
| `lw:refresh-tokens` | Every 30 minutes | Refresh Linnworks session tokens |
| `vendor:sync-catalog` | Hourly | Refresh `vendor_products` and Linnworks product mapping data |
| `vendor:sync-inventory` | Every 30 minutes | Refresh the channel-side inventory snapshot |
| `sync:prune-logs --days=90` | Daily at 02:30 | Remove old synchronization history |

All scheduled commands use `withoutOverlapping()`. The cache/lock service must therefore be available to the scheduler.

Run Laravel's scheduler once per minute, for example:

```cron
* * * * * cd /var/www/linnworks-channel && php artisan schedule:run >> /dev/null 2>&1
```

Alternatively, supervise `php artisan schedule:work`. Only one scheduler should be authoritative unless shared distributed locks are configured and tested.

## Deployment

A normal deployment should perform these steps:

1. Put the application in maintenance mode when the migration risk requires it.
2. Install production Composer dependencies.
3. Build frontend assets.
4. Run `php artisan migrate --force`.
5. Cache Laravel configuration, routes, and views.
6. Restart long-running queue and scheduler processes.
7. Restore traffic and run health checks.

Representative commands:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

The deployment must preserve writable `storage` and cache directories. Database migrations and backups should be verified before changing production traffic.

## Health Checks and Monitoring

Monitor at least the following:

- Public application health endpoint and TLS certificate expiry.
- Database, cache, and queue connectivity.
- Scheduler heartbeat; alert if no scheduled task has completed within its expected interval.
- Queue depth, failed jobs, and oldest queued-job age.
- Last successful order, catalog, inventory, cancellation, and token-refresh sync per tenant.
- Consecutive the vendor or Linnworks authentication failures.
- the vendor throttling, server errors, and retry exhaustion.
- Linnworks callback validation errors and skipped orders.
- Disk usage and Laravel log growth.

The application records operational results in `sync_logs`, retained for 90 days by default. Requests use an `X-Correlation-ID`, which is included in log context and forwarded to the vendor, allowing a callback and its outbound API request to be traced together.

Recommended alerts include:

- No successful order pull for 15 minutes.
- No scheduler heartbeat for 5 minutes.
- Three consecutive failures for the same tenant and sync type.
- Any invalid or expired Linnworks/the vendor credential response.
- Queue backlog exceeding the normal processing window.
- Database backup failure or restore-test failure.

## Reliability and Data Safety

- vendor connection failures, HTTP 429 responses, and 5xx responses are retried with bounded exponential backoff.
- Authentication, validation, and not-found responses are not blindly retried.
- The order cursor advances only after both order-fetch passes complete successfully.
- Cancellation, refund, listing, and shipment operations have idempotency controls to reduce duplicate external mutations.
- Scheduler overlap locks prevent two copies of the same command from running concurrently.
- Database backups should be encrypted, retained according to business requirements, and restore-tested regularly.

The application database is integration state, not merely a disposable cache. Losing it can lose order cursors, tenant installation state, SKU mappings, idempotency records, and audit history.

## Security

- Require HTTPS for every Linnworks callback and configuration URL.
- Rotate any credential that has appeared in historical logs.
- Redact authorization tokens, API keys, account tokens, and configuration values from logs.
- Restrict database and cache services to the private network.
- Use least-privilege database and deployment identities.
- Restrict mock endpoints to local/development environments before production deployment.
- Disable `APP_DEBUG` in production.
- Keep Laravel, PHP, Composer, Node, and base operating-system packages patched.
- Define a documented credential-rotation and tenant-offboarding procedure.

Deleting a Linnworks channel configuration can invoke `ConfigDeleted`, which marks the corresponding tenant inactive. Deleting the Linnworks application itself must not be assumed to call the integration. A reconciliation or administrative cleanup procedure is still required for stale tenants.

## Production Readiness Checklist

### Application

- [x] Order pull into local channel storage
- [x] Linnworks order polling endpoint
- [x] Product/catalog storage and product feed endpoint
- [x] Inventory and price update callbacks
- [x] Listing create, update, deactivate, delete, and feed-status callbacks
- [x] Shipment/tracking, cancellation, and refund callbacks
- [x] SKU/reference mapping support
- [x] Tenant filtering, sync logging, correlation IDs, retries, and basic idempotency
- [ ] Validate every request and response against the real the vendor production contract
- [ ] Test partial shipments, partial cancellations, and partial refunds
- [ ] Complete returns handling if required by the account workflow
- [ ] Decide requirements for multi-location inventory
- [ ] Complete category, image, variation, and richer catalog handling if required
- [ ] Add reconciliation for stale Linnworks installations and remote deletions

### Testing

- [x] Unit and feature coverage for the principal channel callbacks
- [ ] Add explicit duplicate-request tests for listing, despatch, cancellation, and refund idempotency
- [ ] Run a complete Linnworks UI flow: install, configure, map SKU, create listing, import order, update inventory, ship, cancel/refund, and uninstall
- [ ] Run failure tests for timeouts, 429 responses, 5xx responses, invalid credentials, and database/cache outages
- [ ] Run load and pagination tests using production-scale order and catalog volumes

### Infrastructure

- [ ] Deploy to a stable HTTPS domain
- [ ] Provision production database, cache, queues, and private networking
- [ ] Configure supervised scheduler and queue workers
- [ ] Configure centralized logs, metrics, alerts, and scheduler heartbeat
- [ ] Configure encrypted backups and perform a restore test
- [ ] Establish staging and production environments with separate credentials
- [ ] Restrict or remove the vendor mock routes in production
- [ ] Add a rollback procedure and verify zero/low-downtime migrations
- [ ] Document incident response, credential rotation, and tenant cleanup

## Recommended Next Work

The recommended implementation order is:

1. Clean up legacy commands, fake OAuth behavior, stale documentation, and obsolete commented mock code.
2. Add explicit idempotency and failure-path tests.
3. Perform the complete Linnworks UI flow against the stateful mock.
4. Validate and adjust all the vendor payloads against real API credentials and contracts.
5. Provision production infrastructure, monitoring, backups, and operational runbooks.

Production launch should remain blocked until the real the vendor contract has been exercised successfully and the full Linnworks workflow has passed end-to-end testing.
