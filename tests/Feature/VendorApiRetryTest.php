<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\VendorApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VendorApiRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_failures_are_not_retried(): void
    {
        [$service, $url] = $this->service();
        Http::fake([$url => Http::response(['error' => 'invalid'], 422)]);
        $this->assertNull($service->getOrders());
        Http::assertSentCount(1);
        $this->assertSame('validation', $service->lastFailure()['classification']);
    }

    public function test_server_failures_are_retried(): void
    {
        [$service, $url] = $this->service();
        Http::fakeSequence($url)->push(['error' => 'temporary'], 500)->push(['data' => [['id' => 'O-1']]], 200);
        $this->assertSame([['id' => 'O-1']], $service->getOrders());
        Http::assertSentCount(2);
        $this->assertNull($service->lastFailure());
    }

    private function service(): array
    {
        $tenant = Tenant::create([
            'linnworks_user_id' => uniqid('retry-', true), 'linnworks_email' => 'retry@example.com',
            'linnworks_application_token' => '', 'linnworks_token' => '', 'linnworks_server' => 'https://api.linnworks.net',
            'authorization_token' => Tenant::generateAuthorizationToken(), 'vendor_api_key' => 'key',
            'vendor_account_id' => 'account', 'vendor_account_token' => 'token', 'vendor_environment' => 'stage', 'active' => true,
        ]);
        config(['services.vendor_api.base_url' => 'https://vendor.test']);
        return [new VendorApiService($tenant), 'https://vendor.test/accounts/account/orders*'];
    }
}
