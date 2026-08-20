<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinnworksService
{
    private const AUTH_URL = 'https://api.linnworks.net/api/Auth/AuthorizeByApplication';

    private ?string $lastError = null;

    /**
     * Exchange a user-provided application token for Linnworks session data.
     * Returns the raw decoded response or null on failure.
     */
    public function authorizeByApplication(string $applicationToken): ?array
    {
        $this->lastError = null;

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(8)
                ->timeout(15)
                ->retry(1, 200)
                ->post(self::AUTH_URL, [
                    'ApplicationId'     => config('services.linnworks.app_id'),
                    'ApplicationSecret' => config('services.linnworks.app_secret'),
                    'Token'             => $applicationToken,
                ]);

            if ($response->failed()) {
                $jsonMessage = $response->json('Message');
                $this->lastError = is_string($jsonMessage) && $jsonMessage !== ''
                    ? $jsonMessage
                    : 'Linnworks authorization failed.';

                Log::warning('Linnworks AuthorizeByApplication failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::error('Linnworks AuthorizeByApplication exception: ' . $e->getMessage());
            return null;
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
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
