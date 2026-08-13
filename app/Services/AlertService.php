<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertService
{
    private const MAX_ALERT_ATTEMPT = 3; // send Slack only when sync_attempt reaches this

    public function notifyOrderSyncFailure(int $tenantId, string $orderId, string $error, int $attempt): void
    {
        if ($attempt < self::MAX_ALERT_ATTEMPT) {
            return;
        }

        $webhookUrl = config('services.slack.webhook_url');

        if (!$webhookUrl) {
            Log::warning('Slack webhook URL not configured, skipping alert.');
            return;
        }

        $env = app()->environment();
        $message = "[{$env}] :rotating_light: *Target Plus sync failure* after {$attempt} attempts\n"
            . "Tenant ID: `{$tenantId}` | Order ID: `{$orderId}`\n"
            . "Error: `{$error}`";

        try {
            Http::post($webhookUrl, ['text' => $message]);
        } catch (\Throwable $e) {
            Log::error('Failed to send Slack alert: ' . $e->getMessage());
        }
    }

    public function notifyGeneral(string $message): void
    {
        $webhookUrl = config('services.slack.webhook_url');

        if (!$webhookUrl) {
            return;
        }

        try {
            Http::post($webhookUrl, ['text' => $message]);
        } catch (\Throwable $e) {
            Log::error('Failed to send Slack alert: ' . $e->getMessage());
        }
    }
}
