<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\VendorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannelListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_linnworks_can_create_a_listing_and_poll_its_feed(): void
    {
        $tenant = Tenant::create([
            'linnworks_user_id' => 'listing-user',
            'linnworks_email' => 'listing@example.com',
            'linnworks_application_token' => 'app-token',
            'linnworks_token' => 'session-token',
            'linnworks_server' => 'https://api.linnworks.net',
            'authorization_token' => Tenant::generateAuthorizationToken(),
            'vendor_api_key' => 'api-key',
            'vendor_account_id' => 'account-1',
            'vendor_account_token' => 'account-token',
            'vendor_environment' => 'stage',
            'active' => true,
        ]);

        config(['services.vendor_api.base_url' => 'https://vendor.test']);
        Http::fake([
            'https://vendor.test/accounts/account-1/products' => Http::response([
                'vendor_reference' => 'MOCK-NEW-1',
                'external_id' => 'MOCK-NEW-1',
                'status' => 'ACTIVE',
            ], 201),
        ]);

        $response = $this->postJson('/api/Listing/ListingUpdate', [
            'AuthorizationToken' => $tenant->authorization_token,
            'Type' => 0,
            'Listings' => [[
                'TemplateId' => 42,
                'SKU' => 'NEW-SKU',
                'Title' => 'New Product',
                'Description' => 'Created in Linnworks',
                'Quantity' => 7,
                'Price' => 24.95,
                'Categories' => ['3'],
                'Attributes' => [[
                    'AttributeID' => 'Brand',
                    'AttributeValue' => 'Example Brand',
                    'IsCustomAttribute' => false,
                ]],
                'Images' => [],
            ]],
        ])->assertOk()->assertJsonPath('Error', null);

        $feedId = $response->json('ChannelFeedId');
        $this->assertNotEmpty($feedId);
        $this->assertDatabaseHas('vendor_products', [
            'tenant_id' => $tenant->id,
            'vendor_reference' => 'MOCK-NEW-1',
            'sku' => 'NEW-SKU',
            'available_quantity' => 7,
            'listing_active' => true,
        ]);

        $this->postJson('/api/Listing/CheckFeed', [
            'AuthorizationToken' => $tenant->authorization_token,
            'ChannelFeedId' => $feedId,
        ])->assertOk()
            ->assertJsonPath('Error', null)
            ->assertJsonPath('IsFeedReady', true)
            ->assertJsonPath('ProductFeeds.0.SKU', 'NEW-SKU')
            ->assertJsonPath('ProductFeeds.0.TemplateId', 42)
            ->assertJsonPath('ProductFeeds.0.ExternalListingId', 'MOCK-NEW-1')
            ->assertJsonPath('ProductFeeds.0.Messages', null)
            ->assertJsonPath('ProductFeeds.0.ChannelReferences.0.Reference', 'MOCK-NEW-1');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://vendor.test/accounts/account-1/products'
            && $request['sku'] === 'NEW-SKU');
    }

    public function test_linnworks_can_update_a_listing_when_type_is_enum_name(): void
    {
        $tenant = Tenant::create([
            'linnworks_user_id' => 'listing-update-user',
            'linnworks_email' => 'listing-update@example.com',
            'linnworks_application_token' => 'app-token',
            'linnworks_token' => 'session-token',
            'linnworks_server' => 'https://api.linnworks.net',
            'authorization_token' => Tenant::generateAuthorizationToken(),
            'vendor_api_key' => 'api-key',
            'vendor_account_id' => 'account-1',
            'vendor_account_token' => 'account-token',
            'vendor_environment' => 'stage',
            'active' => true,
        ]);

        VendorProduct::create([
            'tenant_id' => $tenant->id,
            'vendor_reference' => 'MOCK-EXISTING-1',
            'external_id' => 'MOCK-EXISTING-1',
            'sku' => 'EXISTING-SKU',
            'title' => 'Existing Product',
            'status' => 'ACTIVE',
            'available_quantity' => 2,
            'price' => 10.00,
            'listing_active' => true,
            'raw_payload' => [],
        ]);

        config(['services.vendor_api.base_url' => 'https://vendor.test']);
        Http::fake([
            'https://vendor.test/accounts/account-1/products/MOCK-EXISTING-1' => Http::response([
                'vendor_reference' => 'MOCK-EXISTING-1',
                'external_id' => 'MOCK-EXISTING-1',
                'status' => 'ACTIVE',
            ]),
        ]);

        $this->postJson('/api/Listing/ListingUpdate', [
            'AuthorizationToken' => $tenant->authorization_token,
            'Type' => 'UPDATE',
            'Listings' => [[
                'TemplateId' => 84,
                'ExternalListingId' => 'MOCK-EXISTING-1',
                'SKU' => 'EXISTING-SKU',
                'Title' => 'Updated Product',
                'Description' => 'Updated in Linnworks',
                'Quantity' => 9,
                'Price' => 19.95,
                'Categories' => ['3'],
                'Attributes' => [],
                'Images' => [],
            ]],
        ])->assertOk()
            ->assertJsonPath('Error', null);

        $this->assertDatabaseHas('vendor_products', [
            'tenant_id' => $tenant->id,
            'vendor_reference' => 'MOCK-EXISTING-1',
            'sku' => 'EXISTING-SKU',
            'title' => 'Updated Product',
            'available_quantity' => 9,
        ]);

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->url() === 'https://vendor.test/accounts/account-1/products/MOCK-EXISTING-1'
            && $request['sku'] === 'EXISTING-SKU');
    }

    public function test_listing_create_uses_in_app_mock_store_for_mock_vendor_urls(): void
    {
        $tenant = Tenant::create([
            'linnworks_user_id' => 'listing-local-mock-user',
            'linnworks_email' => 'listing-local-mock@example.com',
            'linnworks_application_token' => 'app-token',
            'linnworks_token' => 'session-token',
            'linnworks_server' => 'https://api.linnworks.net',
            'authorization_token' => Tenant::generateAuthorizationToken(),
            'vendor_api_key' => 'api-key',
            'vendor_account_id' => 'account-local-mock',
            'vendor_account_token' => 'account-token',
            'vendor_environment' => 'stage',
            'active' => true,
        ]);

        config(['services.vendor_api.base_url' => 'http://127.0.0.1:8000/mock-vendor']);
        Http::preventStrayRequests();

        $response = $this->postJson('/api/Listing/ListingUpdate', [
            'AuthorizationToken' => $tenant->authorization_token,
            'Type' => 0,
            'Listings' => [[
                'ConfiguratorId' => 1,
                'TemplateId' => 3,
                'ExternalListingId' => null,
                'SKU' => 'SKU-1001.2',
                'Title' => 'Classic Cotton Tee',
                'Description' => '<html><body><p>SKU-1001</p></body></html>',
                'Quantity' => 30,
                'Images' => [['Url' => 'https://example.test/image.jpg', 'Tags' => []]],
                'Price' => 122.0,
                'Categories' => ['2'],
                'ShippingMethods' => [],
                'Attributes' => [],
                'Variations' => [],
                'VariationOptions' => [],
            ]],
            'Settings' => [[
                'ConfiguratorId' => 1,
                'Settings' => [
                    'GeneralSettings' => [['ID' => 'Condition', 'Values' => ['New']]],
                ],
            ]],
        ])->assertOk()
            ->assertJsonPath('Error', null);

        $feedId = $response->json('ChannelFeedId');

        $this->postJson('/api/Listing/CheckFeed', [
            'AuthorizationToken' => $tenant->authorization_token,
            'ChannelFeedId' => $feedId,
        ])->assertOk()
            ->assertJsonPath('Error', null)
            ->assertJsonPath('ProductFeeds.0.SKU', 'SKU-1001.2')
            ->assertJsonPath('ProductFeeds.0.Messages', null);
    }

    public function test_configurator_settings_are_available_without_a_tenant_token(): void
    {
        $this->withHeader('X-Correlation-ID', 'test-correlation-123')
            ->postJson('/api/Listing/GetConfiguratorSettings')
            ->assertOk()
            ->assertHeader('X-Correlation-ID', 'test-correlation-123')
            ->assertJsonPath('Error', null)
            ->assertJsonPath('IsVariationsAllowed', false)
            ->assertJsonPath('Settings.0.ConfigItemId', 'Condition');
    }

    public function test_unmatched_api_routes_return_json_for_external_debugging(): void
    {
        $this->postJson('/api/Listing/UnexpectedListingRoute', ['AuthorizationToken' => 'token'])
            ->assertNotFound()
            ->assertJsonPath('Error', 'Unknown endpoint: api/Listing/UnexpectedListingRoute');
    }
}
