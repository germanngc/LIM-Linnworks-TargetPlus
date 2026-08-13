<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinnworksService
{
    private const AUTH_URL = 'https://api.linnworks.net/api/Auth/AuthorizeByApplication';

    /**
     * Exchange a user-provided application token for Linnworks session data.
     * Returns the raw decoded response or null on failure.
     */
    public function authorizeByApplication(string $applicationToken): ?array
    {
        try {
            $response = Http::asForm()
                ->withHeaders([
                    'Accept'          => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Content-Type'    => 'application/x-www-form-urlencoded; charset=UTF-8',
                ])
                ->post(self::AUTH_URL, [
                    'ApplicationId'     => config('services.linnworks.app_id'),
                    'ApplicationSecret' => config('services.linnworks.app_secret'),
                    'Token'             => $applicationToken,
                ]);

            if ($response->failed()) {
                Log::warning('Linnworks AuthorizeByApplication failed', ['body' => $response->body()]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Linnworks AuthorizeByApplication exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Refresh an expired Linnworks session token.
     * Returns the new token string or null on failure.
     */
    public function refreshToken(Tenant $tenant): ?string
    {
        $data = $this->authorizeByApplication($tenant->linnworks_application_token);

        if (!$data || empty($data['Token'])) {
            return null;
        }

        $tenant->update(['linnworks_token' => $data['Token']]);

        return $data['Token'];
    }
}
