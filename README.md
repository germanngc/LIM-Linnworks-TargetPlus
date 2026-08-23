# Linnworks Channel Integration Skeleton

This project is a Laravel application that connects a Linnworks account to a vendor for order synchronization, catalog sync, inventory updates, and fulfillment status flows.

Channel configuration decisions for Linnworks (Orders/Inventory/Listings) are documented in:

- [docs/linnworks-channel-config-decisions.md](docs/linnworks-channel-config-decisions.md)

End-user documentation is available in:

- [docs/USER_MANUAL.md](docs/USER_MANUAL.md)
- [docs/linnworks-channel-user-workflow.drawio](docs/linnworks-channel-user-workflow.drawio)

The app is designed around a tenant-based setup flow:

- A user starts from Linnworks and authorizes the integration.
- The app creates or updates a tenant record.
- The user enters vendor credentials for that tenant.
- Background jobs keep orders, inventory, and catalog state synchronized.

---

## 1. Prerequisites

Before setting up the integration, make sure you have the following:

- PHP 8.3+
- Composer
- Node.js 18+ and npm
- Docker Desktop or Docker Engine (for the local MySQL container)
- A Linnworks application configured with the correct authorization flow
- A vendor account and API credentials
- A public HTTPS base URL for production deployment

You will also need these values available before the app can fully function:

- `LINNWORKS_APP_ID`
- `LINNWORKS_APP_SECRET`
- `vendor API key`
- `vendor account ID`
- `vendor account token`
- `vendor environment`: `prod` or `stage`

---

## 2. Dependencies

This project uses:

- Laravel 13
- MySQL (local setup via Docker)
- Composer packages from `composer.json`
- Vite/Node packages from `package.json`

Install PHP and frontend dependencies:

```bash
composer install
npm install
```

If you want to run the project setup script included in this repo:

```bash
composer run setup
```

That script will:

- install Composer dependencies
- create `.env` if it does not exist
- generate the Laravel app key
- run migrations
- install npm dependencies
- build the frontend assets

---

## 3. Localhost Setup

### Step 1: Prepare environment variables

Copy the example environment file:

```bash
cp .env.example .env
```

Then update the values in `.env` for local development. The app expects a MySQL database and the Linnworks integration settings.

Example local configuration:

```env
APP_NAME="Linnworks Channel"
APP_ENV=local
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=9308
DB_DATABASE=linnworks_channel
DB_USERNAME=linnworks_channel_usr
DB_PASSWORD=your_db_password

LINNWORKS_APP_ID=your_linnworks_app_id
LINNWORKS_APP_SECRET=your_linnworks_app_secret

SLACK_WEBHOOK_URL=
```

### Step 2: Start the database

This project includes a Docker Compose setup for MariaDB:

```bash
docker compose up -d db
```

The database will be exposed on port `9308`.

### Step 3: Generate app key and migrate

```bash
php artisan key:generate
php artisan migrate
```

### Step 4: Build the front-end assets

```bash
npm run build
```

### Step 5: Run the application

For a local development environment:

```bash
composer run dev
```

This starts:

- Laravel app server
- queue worker
- log viewer
- Vite dev server

The app should be available at:

```text
http://localhost:8000
```

### Step 6: Complete the integration setup flow

The first route loads the Linnworks authorization flow:

```text
/
```

This page expects a `token` query parameter coming from Linnworks. Once validated, the app shows a form for vendor credentials.

The required fields are:

- `vendor_api_key`
- `vendor_account_id`
- `vendor_account_token`
- `vendor_environment` (`prod` or `stage`)

After submission, the tenant is marked as active and the app starts processing integration jobs.

### Step 7: Background jobs and scheduling

The app schedules sync jobs in `routes/console.php`:

- Pull vendor orders every 30 minutes
- Push cancellations every 15 minutes
- Refresh Linnworks tokens every 15 minutes
- Sync catalog daily
- Sync inventory every 30 minutes
- Sync listings every 30 minutes

For local development, the scheduler is normally run via:

```bash
php artisan schedule:run
```

or by using the Laravel dev stack while working in a local environment.

---

## 4. Production Setup

### Step 1: Prepare the production environment

Set the production values in `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example.com

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

LINNWORKS_APP_ID=your_linnworks_app_id
LINNWORKS_APP_SECRET=your_linnworks_app_secret
```

Use a real database and ensure the application can access it over the network.

### Step 2: Install dependencies on the server

```bash
composer install --no-interaction --prefer-dist --optimize-autoloader
npm install
npm run build
```

Then generate the app key and run migrations:

```bash
php artisan key:generate
php artisan migrate --force
```

### Step 3: Configure HTTPS and public URL

Make sure the environment uses a public HTTPS domain. The app uses `APP_URL` for generated URLs, session handling, and filesystem links.

Example:

```bash
APP_URL=https://your-domain.example.com
```

### Step 4: Configure Linnworks callback settings

The app initializes from the Linnworks authorization entry point and expects the public URL to be reachable.

Confirm that the Linnworks app is configured to redirect to the same domain and route used by this project, especially the root page:

```text
https://your-domain.example.com/
```

### Step 5: Configure the the vendor tenant

Once the app is live, open the root route from the Linnworks authorization flow and complete the setup form for each tenant with:

- `vendor_api_key`
- `vendor_account_id`
- `vendor_account_token`
- `vendor_environment` (`prod` or `stage`)

The app stores these values encrypted and uses them when connecting to vendor.

### Step 6: Run background workers and scheduler

In production, you will need:

- a queue worker running in the background
- the Laravel scheduler running on a cron job

Example cron entry:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

And a queue worker process, for example:

```bash
php artisan queue:work --tries=3 --timeout=90
```

### Step 7: Validate the integration

After setup, confirm the following work correctly:

- Linnworks token validation succeeds
- vendor credentials save correctly
- tenant is marked `active`
- orders are imported from vendor
- inventory and catalog sync jobs complete
- dispatch updates flow back to vendor

---

## Common Notes

- The app intentionally encrypts vendor credentials on the `Tenant` model before storing them.
- The `vendor_environment` value controls the base URL:
  - `prod` -> `https://api.vendor.com/channel_api/v1`
  - `stage` -> `https://stage-api.vendor.com/channel_api/v1`
- The app depends on the public URL being reachable from the Linnworks authorization flow.

---

## Useful Commands

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
composer run dev
php artisan queue:work
php artisan schedule:run
```

If you need to troubleshoot the app, check the Laravel logs in `storage/logs` and validate the environment variables in `.env` before restarting the services.
