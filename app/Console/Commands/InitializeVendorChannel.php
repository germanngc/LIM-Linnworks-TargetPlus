<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InitializeVendorChannel extends Command
{
    protected $signature = 'vendor:init
        {--name= : Vendor or marketplace display name}
        {--docs-url= : Vendor API documentation URL}
        {--auth= : Authentication type: api_key, oauth2, bearer, hmac, mtls, basic, other}
        {--prod-url= : Production API base URL}
        {--stage-url= : Sandbox/staging API base URL}
        {--capability=* : Enabled capability. Repeatable. Supported: orders,catalog,listings,inventory,price,shipment,cancellation,refunds}
        {--sku-field=sku : Vendor API field that maps to Linnworks SKU}
        {--product-id-field=vendor_reference : Vendor API field that maps to the vendor product/listing identifier}
        {--write-env : Update .env with APP_NAME, VENDOR_API_BASE_URL, and Linnworks source labels}
        {--force : Overwrite an existing generated vendor profile}';

    protected $description = 'Initialize this Linnworks channel skeleton for a new vendor integration.';

    private const CAPABILITIES = [
        'orders',
        'catalog',
        'listings',
        'inventory',
        'price',
        'shipment',
        'cancellation',
        'refunds',
    ];

    public function handle(): int
    {
        $interactive = $this->input->isInteractive();

        $vendorName = $this->stringOption('name') ?: ($interactive ? $this->ask('Vendor or marketplace name', 'Vendor') : 'Vendor');
        $docsUrl = $this->stringOption('docs-url') ?: ($interactive ? $this->ask('Vendor API docs URL', '') : '');
        $authType = $this->normalizeAuthType($this->stringOption('auth') ?: ($interactive ? $this->choice('Authentication type', ['api_key', 'oauth2', 'bearer', 'hmac', 'mtls', 'basic', 'other'], 'api_key') : 'api_key'));
        $prodUrl = $this->stringOption('prod-url') ?: ($interactive ? $this->ask('Production API base URL', '') : '');
        $stageUrl = $this->stringOption('stage-url') ?: ($interactive ? $this->ask('Sandbox/staging API base URL', '') : '');

        $capabilities = $this->option('capability');
        if ($interactive && empty($capabilities)) {
            $capabilities = $this->askCapabilities();
        }
        $capabilities = $this->normalizeCapabilities($capabilities ?: ['orders', 'catalog', 'listings', 'inventory', 'shipment']);

        $skuField = $this->stringOption('sku-field') ?: 'sku';
        $productIdField = $this->stringOption('product-id-field') ?: 'vendor_reference';

        $profile = [
            'vendor' => [
                'name' => $vendorName,
                'slug' => Str::slug($vendorName ?: 'vendor'),
                'docs_url' => $docsUrl,
                'auth_type' => $authType,
                'production_base_url' => $prodUrl,
                'staging_base_url' => $stageUrl,
            ],
            'capabilities' => collect(self::CAPABILITIES)
                ->mapWithKeys(fn (string $capability) => [$capability => in_array($capability, $capabilities, true)])
                ->all(),
            'mapping' => [
                'sku_field' => $skuField,
                'product_id_field' => $productIdField,
            ],
            'next_steps' => $this->nextSteps($capabilities, $authType),
            'generated_at' => now()->toIso8601String(),
        ];

        $profilePath = storage_path('app/vendor-channel/profile.json');
        if (File::exists($profilePath) && !$this->option('force')) {
            $this->error("Profile already exists at {$profilePath}. Use --force to overwrite it.");
            return Command::FAILURE;
        }

        File::ensureDirectoryExists(dirname($profilePath));
        File::put($profilePath, json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        if ($this->option('write-env') || ($interactive && $this->confirm('Update .env with basic vendor defaults?', false))) {
            $this->updateEnv($vendorName, $prodUrl ?: $stageUrl);
        }

        $this->info('Vendor channel profile generated.');
        $this->line($profilePath);
        $this->newLine();
        $this->table(['Capability', 'Enabled'], collect(self::CAPABILITIES)->map(fn ($capability) => [
            $capability,
            Arr::get($profile, "capabilities.{$capability}") ? 'yes' : 'no',
        ])->all());

        $this->newLine();
        $this->line('Next steps:');
        foreach ($profile['next_steps'] as $step) {
            $this->line("- {$step}");
        }

        return Command::SUCCESS;
    }

    private function stringOption(string $name): string
    {
        return trim((string) ($this->option($name) ?? ''));
    }

    private function normalizeAuthType(string $authType): string
    {
        $authType = strtolower(trim($authType));

        return in_array($authType, ['api_key', 'oauth2', 'bearer', 'hmac', 'mtls', 'basic', 'other'], true)
            ? $authType
            : 'other';
    }

    private function normalizeCapabilities(array $capabilities): array
    {
        return collect($capabilities)
            ->flatMap(fn ($item) => explode(',', (string) $item))
            ->map(fn ($item) => strtolower(trim($item)))
            ->filter(fn ($item) => in_array($item, self::CAPABILITIES, true))
            ->unique()
            ->values()
            ->all();
    }

    private function askCapabilities(): array
    {
        $this->line('Enable capabilities. Answer yes/no for each one.');

        return collect(self::CAPABILITIES)
            ->filter(fn (string $capability) => $this->confirm("Enable {$capability}?", in_array($capability, ['orders', 'catalog', 'listings', 'inventory', 'shipment'], true)))
            ->values()
            ->all();
    }

    private function nextSteps(array $capabilities, string $authType): array
    {
        $steps = [
            'Read .ai/skills/linnworks-channel-integration/SKILL.md and the implementation playbook.',
            'Adapt app/Services/VendorApiService.php to the vendor API auth, endpoints, pagination, retries, and response shape.',
            'Adapt app/Services/MockVendorStore.php so local mock payloads match the vendor minimum payloads.',
            'Update Linnworks config labels in ChannelIntegrationController if the vendor uses different credential names.',
        ];

        if ($authType === 'oauth2') {
            $steps[] = 'Add OAuth authorize/callback/token refresh handling before testing Linnworks config.';
        }

        if (in_array('orders', $capabilities, true)) {
            $steps[] = 'Map vendor orders in OrderMapperService and verify /api/Order/Orders returns valid line numbers and SKUs.';
        }

        if (in_array('listings', $capabilities, true)) {
            $steps[] = 'Map Linnworks ListingUpdate/Listings payloads into vendor create/update/deactivate calls and verify CheckFeed polling.';
        }

        if (in_array('shipment', $capabilities, true)) {
            $steps[] = 'Map Linnworks despatch/process-order data into vendor shipment/tracking confirmation and keep duplicate protection.';
        }

        $steps[] = 'Run php artisan test, then test install/config/listing/order/despatch flows through Linnworks with an HTTPS URL.';

        return $steps;
    }

    private function updateEnv(string $vendorName, string $baseUrl): void
    {
        $envPath = base_path('.env');
        if (!File::exists($envPath)) {
            File::copy(base_path('.env.example'), $envPath);
        }

        $contents = File::get($envPath);
        $replacements = [
            'APP_NAME' => '"' . addcslashes("Linnworks {$vendorName}", '"') . '"',
            'LINNWORKS_ORDER_SOURCE' => strtoupper(Str::slug($vendorName ?: 'vendor', '_')),
            'LINNWORKS_ORDER_SUBSOURCE' => '"' . addcslashes($vendorName ?: 'Vendor', '"') . '"',
        ];

        if ($baseUrl !== '') {
            $replacements['VENDOR_API_BASE_URL'] = $baseUrl;
        }

        foreach ($replacements as $key => $value) {
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
            $line = "{$key}={$value}";
            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, $line, $contents)
                : rtrim($contents) . PHP_EOL . $line . PHP_EOL;
        }

        File::put($envPath, $contents);
        $this->warn('.env updated. Review it before running production-like tests.');
    }
}
