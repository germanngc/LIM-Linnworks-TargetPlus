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
        'target_api_key',
        'target_seller_id',
        'target_seller_token',
        'target_environment',
        'active',
        'last_sync_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    // Encrypt credentials transparently on read/write
    public function getTargetApiKeyAttribute(string $value): string
    {
        return decrypt($value);
    }

    public function setTargetApiKeyAttribute(string $value): void
    {
        $this->attributes['target_api_key'] = encrypt($value);
    }

    public function getTargetSellerIdAttribute(string $value): string
    {
        return decrypt($value);
    }

    public function setTargetSellerIdAttribute(string $value): void
    {
        $this->attributes['target_seller_id'] = encrypt($value);
    }

    public function getTargetSellerTokenAttribute(string $value): string
    {
        return decrypt($value);
    }

    public function setTargetSellerTokenAttribute(string $value): void
    {
        $this->attributes['target_seller_token'] = encrypt($value);
    }

    public function orders()
    {
        return $this->hasMany(TpOrder::class);
    }

    public static function generateAuthorizationToken(): string
    {
        return (string) Str::uuid();
    }

    public function targetBaseUrl(): string
    {
        $override = config('services.target_plus.base_url');

        if (is_string($override) && $override !== '') {
            return rtrim($override, '/');
        }

        return $this->target_environment === 'stage'
            ? 'https://stage-api.target.com/seller_orders/v1'
            : 'https://api.target.com/seller_orders/v1';
    }

    public function hasTargetPlusCredentials(): bool
    {
        foreach ([$this->target_api_key, $this->target_seller_id, $this->target_seller_token] as $value) {
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
            ->filter(fn (Tenant $tenant) => $tenant->hasTargetPlusCredentials())
            ->values();
    }
}
