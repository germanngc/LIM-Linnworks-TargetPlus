<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannelConfigTestTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_config_bootstraps_tenant_when_token_is_unknown(): void
    {
        $token = 'bootstrap-token-123';

        $this->postJson('/api/Config/UserConfig', ['AuthorizationToken' => $token])
            ->assertOk()
            ->assertJsonPath('Error', null)
            ->assertJsonPath('StepName', 'AddCredentials');

        $tenant = Tenant::query()->where('authorization_token', $token)->first();

        $this->assertNotNull($tenant);
        $this->assertTrue((bool) $tenant->active);

        $this->postJson('/api/Config/ConfigTest', ['AuthorizationToken' => $token])
            ->assertOk()
            ->assertJson(['Success' => false, 'Error' => 'Target Plus credentials are incomplete.']);
    }

    public function test_user_config_is_available_without_token_for_discovery_get_config(): void
    {
        $this->postJson('/api/Config/UserConfig', [])
            ->assertOk()
            ->assertJsonPath('Error', null)
            ->assertJsonPath('StepName', 'AddCredentials')
            ->assertJsonPath('AccountName', 'Target Plus Channel')
            ->assertJsonPath('ConfigItems.0.ConfigItemId', 'APIKey')
            ->assertJsonPath('ConfigItems.0.HidesHeaderAttribute', false);
    }

    public function test_config_test_requires_an_authorization_token(): void
    {
        config(['services.target_plus.base_url' => 'https://target.test']);
        Http::fake(['https://target.test/sellers/seller-123/orders*' => Http::response(['data' => []])]);
        Tenant::create([
            'linnworks_user_id' => 'user-1',
            'linnworks_email' => 'test@example.com',
            'linnworks_application_token' => 'app-token',
            'linnworks_token' => 'linnworks-session-token',
            'linnworks_server' => 'https://eu-ext.linnworks.net',
            'authorization_token' => 'cfg-token-123',
            'target_api_key' => 'api-key',
            'target_seller_id' => 'seller-123',
            'target_seller_token' => 'seller-token',
            'target_environment' => 'stage',
            'active' => true,
        ]);

        $this->postJson('/api/Config/ConfigTest', ['AuthorizationToken' => 'cfg-token-123'])
            ->assertOk()
            ->assertJson(['Success' => true]);

        $this->postJson('/api/Config/ConfigTest', [])
            ->assertStatus(401)
            ->assertJson(['Error' => 'Missing AuthorizationToken']);
    }
}
