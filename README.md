# Linnworks Channel Integration Skeleton

This repository is a Laravel skeleton for building Linnworks External Channel integrations.

It starts generic on purpose. A new vendor integration should begin by running `php artisan vendor:init`, answering the vendor capability questions, then adapting `VendorApiService`, the mocks, and the mapping layer to the vendor API.

Useful project docs:

- [docs/linnworks-channel-config-decisions.md](docs/linnworks-channel-config-decisions.md)
- [docs/USER_MANUAL.md](docs/USER_MANUAL.md)
- [docs/linnworks-channel-user-workflow.drawio](docs/linnworks-channel-user-workflow.drawio)
- [.ai/agents/linnworks-channel-initializer.md](.ai/agents/linnworks-channel-initializer.md)
- [.ai/skills/linnworks-channel-integration/SKILL.md](.ai/skills/linnworks-channel-integration/SKILL.md)

## What this skeleton already includes

- Linnworks installation/configuration endpoints.
- Order polling endpoint for Linnworks: `/api/Order/Orders`.
- Product/catalog endpoint for Linnworks SKU mapping.
- Listing create/update/delete feed handling with `ChannelFeedId` and `CheckFeed`.
- Inventory update handling.
- Despatch/shipment confirmation.
- Cancellation and refund post-sale mock flows.
- Stateful local mock vendor API.
- Tenant-based credential storage.
- Scheduler commands for order/catalog/inventory/cancellation sync.
- AI-readable skill and agent instructions for Codex, Claude, Cursor, and Copilot.

The important architecture decision: vendor orders are pulled into `vendor_orders` first. Linnworks does not fetch directly from the vendor on demand; Linnworks polls this app, and this app returns locally cached eligible orders.

## Initialize a new vendor integration

Start on a fresh branch:

```bash
git switch -c vendor/example-market
```

Run the initializer:

```bash
php artisan vendor:init
```

The command asks for:

- vendor name;
- API docs URL;
- auth type;
- production and sandbox API URLs;
- supported capabilities;
- SKU field;
- product/listing identifier field.

It writes:

```text
storage/app/vendor-channel/profile.json
```

That profile becomes the implementation brief for humans and AI assistants.

You can also run it non-interactively:

```bash
php artisan vendor:init \
  --name="Example Market" \
  --docs-url="https://example.test/docs" \
  --auth=bearer \
  --prod-url="https://api.example.test" \
  --stage-url="https://sandbox.example.test" \
  --capability=orders \
  --capability=catalog \
  --capability=listings \
  --capability=inventory \
  --capability=shipment \
  --write-env \
  --force
```

After initialization, adapt these first:

- [app/Services/VendorApiService.php](app/Services/VendorApiService.php)
- [app/Services/MockVendorStore.php](app/Services/MockVendorStore.php)
- [app/Services/OrderMapperService.php](app/Services/OrderMapperService.php)
- [app/Http/Controllers/ChannelIntegrationController.php](app/Http/Controllers/ChannelIntegrationController.php)
- [routes/mock.php](routes/mock.php)
- [config/services.php](config/services.php)

## Local development prerequisites

Required:

- PHP 8.3+
- Composer
- Node.js 18+ and npm
- MySQL/MariaDB or SQLite for very small smoke tests
- A public HTTPS tunnel when testing with Linnworks

Optional but recommended:

- Docker Desktop or Docker Engine
- ngrok
- Redis, if you want production-like cache/queue behavior

Install dependencies:

```bash
composer install
npm install
```

Create `.env`:

```bash
cp .env.example .env
php artisan key:generate
```

## Local database options

### Option A: Docker MariaDB

Use this when you want the closest local setup to production.

```bash
docker compose up -d db
```

Then configure `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=9308
DB_DATABASE=linnworks_channel
DB_USERNAME=linnworks_channel_usr
DB_PASSWORD=your_db_password
```

Run migrations:

```bash
php artisan migrate
```

If you see database lock errors from Laravel scheduler commands, verify the DB container is running and reachable.

### Option B: Native MySQL/MariaDB

Install MySQL locally, then create a database and user:

```sql
CREATE DATABASE linnworks_channel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'linnworks_channel_usr'@'localhost' IDENTIFIED BY 'your_db_password';
GRANT ALL PRIVILEGES ON linnworks_channel.* TO 'linnworks_channel_usr'@'localhost';
FLUSH PRIVILEGES;
```

Configure `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=linnworks_channel
DB_USERNAME=linnworks_channel_usr
DB_PASSWORD=your_db_password
```

Then run:

```bash
php artisan migrate
```

### Option C: SQLite

Use this only for quick local smoke tests. For Linnworks async testing, MySQL/MariaDB is safer because scheduler locks and queue/database behavior better match production.

```bash
touch database/database.sqlite
```

Configure `.env`:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

Then run:

```bash
php artisan migrate
```

## Run the app locally

Build assets:

```bash
npm run build
```

Run the full development stack:

```bash
composer run dev
```

This normally starts:

- Laravel app server;
- queue worker;
- log viewer;
- Vite dev server.

Or run pieces manually:

```bash
php artisan serve --host=127.0.0.1 --port=8000
php artisan queue:work
php artisan schedule:work
```

The local app should be available at:

```text
http://127.0.0.1:8000
```

## Expose localhost to Linnworks with ngrok

Linnworks must reach your local app through HTTPS. ngrok is the easiest local option.

Install ngrok:

```bash
brew install ngrok/ngrok/ngrok
```

Authenticate ngrok once:

```bash
ngrok config add-authtoken YOUR_NGROK_TOKEN
```

Expose Laravel:

```bash
ngrok http 8000
```

Copy the HTTPS URL, then update `.env`:

```env
APP_URL=https://your-ngrok-domain.ngrok-free.app
VENDOR_API_BASE_URL=https://your-ngrok-domain.ngrok-free.app/mock-vendor
LINNWORKS_CHANNEL_INTEGRATION=true
```

Then clear cached config:

```bash
php artisan optimize:clear
```

Important: if the ngrok URL changes, Linnworks may continue calling the old URL. Update the Linnworks app/channel configuration or reinstall the channel with the new URL.

## Configure Linnworks

You need a Linnworks application configured for External Channel callbacks.

At minimum:

- the app URL must point to your public HTTPS `APP_URL`;
- the install/config route must open this app;
- Linnworks must be able to call the `/api/...` channel endpoints;
- the app manifest must include the permissions needed for orders, inventory, listings, and post-sale actions you enable.

During setup, Linnworks opens the app with a short-lived token. Complete the setup quickly; the authorization token can expire in seconds.

The setup form stores:

- vendor API key;
- vendor account ID;
- vendor account token;
- vendor environment: `stage` or `prod`.

These are stored encrypted on the `tenants` table.

## Mock-first Linnworks test flow

Use the built-in mock vendor before touching a real vendor API.

Set:

```env
VENDOR_API_BASE_URL=https://your-ngrok-domain.ngrok-free.app/mock-vendor
```

Reset mock state:

```http
DELETE /mock-vendor/accounts/{accountId}/_state
```

Create a fresh mock order:

```bash
php artisan vendor:mock-order {tenant_id}
```

Then wait for Linnworks’ external order download job. Linnworks jobs can take 5-20 minutes. A successful order import usually shows logs like:

```text
Channel returned 1 number of orders in page 1
Saving 1 Orders for page 1
```

On this app you should see:

```text
ChannelIntegration Orders returned
```

Test these flows in order:

1. install/config screen opens;
2. config test succeeds;
3. create mock order and import it into Linnworks;
4. create a listing from Linnworks and wait for `CheckFeed`;
5. update the listing;
6. delete/close the listing;
7. update inventory from Linnworks;
8. process/despatch the order with tracking;
9. cancellation and refund, if enabled.

## Scheduled jobs

Schedules are defined in [routes/console.php](routes/console.php).

Current defaults:

- `vendor:pull-orders` every 5 minutes;
- `vendor:push-cancellations` every 15 minutes;
- `lw:refresh-tokens` every 30 minutes;
- `vendor:sync-catalog` hourly;
- `vendor:sync-inventory` every 30 minutes;
- `sync:prune-logs --days=90` daily.

For local testing:

```bash
php artisan schedule:run
```

For continuous local testing:

```bash
php artisan schedule:work
```

For production, run the scheduler every minute:

```cron
* * * * * cd /var/www/linnworks-channel && php artisan schedule:run >> /dev/null 2>&1
```

## Production setup

Production requires:

- public HTTPS domain;
- durable MySQL/MariaDB database;
- queue worker;
- scheduler;
- secret manager for API keys and tokens;
- centralized logs and alerts;
- stable `APP_URL`.

Example production `.env` shape:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://integration.example.com

DB_CONNECTION=mysql
DB_HOST=database-host
DB_PORT=3306
DB_DATABASE=linnworks_channel
DB_USERNAME=linnworks_channel_usr
DB_PASSWORD=use-a-secret-manager

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database

LINNWORKS_APP_ID=...
LINNWORKS_APP_SECRET=...
LINNWORKS_CHANNEL_INTEGRATION=true
LINNWORKS_ORDER_SOURCE=VENDOR_CHANNEL
LINNWORKS_ORDER_SUBSOURCE="Vendor"

VENDOR_API_BASE_URL=https://api.vendor.example
```

Deploy normally:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Run a worker:

```bash
php artisan queue:work --tries=3 --timeout=90
```

## Troubleshooting

Check logs first:

```bash
tail -f storage/logs/laravel.log
```

Common gotchas:

- If Linnworks shows `ERR_NGROK_3200`, the ngrok tunnel is offline or Linnworks has an old URL.
- If Linnworks order download completes with no channel logs, Linnworks probably did not call this app URL.
- If `/api/Order/Orders` returns zero orders, check `UTCTimeFrom`; Linnworks only asks for orders updated after that cursor.
- If listing creation appears stuck, wait for the `CheckFeed` poll. This can take several minutes.
- If a listing feed fails, check that product feed results include valid `TemplateId`, SKU, external listing ID, and `Messages`.
- If scheduler commands fail with DB lock errors, verify DB connectivity and `CACHE_STORE`.
- Never log raw API keys, account tokens, Linnworks tokens, or customer-sensitive payment data.

## Useful commands

```bash
php artisan vendor:init
php artisan vendor:mock-order {tenant_id}
php artisan vendor:pull-orders
php artisan vendor:sync-catalog
php artisan vendor:sync-inventory
php artisan vendor:sync-listings
php artisan vendor:push-cancellations
php artisan schedule:run
php artisan queue:work
php artisan test
```
