<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class VendorInitCommandTest extends TestCase
{
    public function test_it_generates_a_vendor_initialization_profile_non_interactively(): void
    {
        $path = storage_path('app/vendor-channel/profile.json');
        File::delete($path);

        $this->artisan('vendor:init', [
            '--name' => 'Example Market',
            '--docs-url' => 'https://example.test/docs',
            '--auth' => 'bearer',
            '--prod-url' => 'https://api.example.test',
            '--stage-url' => 'https://sandbox.example.test',
            '--capability' => ['orders', 'inventory', 'shipment'],
            '--force' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();

        $this->assertTrue(File::exists($path));

        $profile = json_decode(File::get($path), true);

        $this->assertSame('Example Market', $profile['vendor']['name']);
        $this->assertSame('https://example.test/docs', $profile['vendor']['docs_url']);
        $this->assertSame('bearer', $profile['vendor']['auth_type']);
        $this->assertTrue($profile['capabilities']['orders']);
        $this->assertTrue($profile['capabilities']['inventory']);
        $this->assertTrue($profile['capabilities']['shipment']);
        $this->assertFalse($profile['capabilities']['refunds']);
    }
}
