<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Tenant extends Model
{
    protected $fillable = [
        'linnworks_user_id',
        'linnworks_email',
        'linnworks_application_token',
        'linnworks_token',
        'linnworks_server',
        'authorization_token',
        'vendor_api_key',
        'vendor_account_id',
        'vendor_account_token',
        'vendor_environment',
        'active',
        'last_sync_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    // Encrypt credentials transparently on read/write
    public function getVendorApiKeyAttribute(string $value): string
    {
        return decrypt($value);
    }

    public function setVendorApiKeyAttribute(string $value): void
    {
        $this->attributes['vendor_api_key'] = encrypt($value);
    }

    public function getVendorAccountIdAttribute(string $value): string
    {
        return decrypt($value);
    }

    public function setVendorAccountIdAttribute(string $value): void
    {
        $this->attributes['vendor_account_id'] = encrypt($value);
    }

    public function getVendorAccountTokenAttribute(string $value): string
    {
        return decrypt($value);
    }

    public function setVendorAccountTokenAttribute(string $value): void
    {
        $this->attributes['vendor_account_token'] = encrypt($value);
    }

    public function orders()
    {
        return $this->hasMany(VendorOrder::class);
    }

    public static function generateAuthorizationToken(): string
    {
        return (string) Str::uuid();
    }

    public function vendorBaseUrl(): string
    {
        $override = config('services.vendor_api.base_url');

        if (is_string($override) && $override !== '') {
            return rtrim($override, '/');
        }

        return $this->vendor_environment === 'stage'
            ? 'https://stage-api.vendor.com/channel_api/v1'
            : 'https://api.vendor.com/channel_api/v1';
    }

    public function hasVendorCredentials(): bool
    {
        foreach ([$this->vendor_api_key, $this->vendor_account_id, $this->vendor_account_token] as $value) {
            if (!is_string($value) || trim($value) === '' || str_starts_with($value, '_pending_')) {
                return false;
            }
        }
        return true;
    }

    public static function syncable(?int $tenantId = null): Collection
    {
        return static::query()
            ->where('active', true)
            ->when($tenantId !== null, fn ($query) => $query->whereKey($tenantId))
            ->get()
            ->filter(fn (Tenant $tenant) => $tenant->hasVendorCredentials())
            ->values();
    }
}
