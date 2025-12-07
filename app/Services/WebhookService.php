<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

/**
 * WebhookService
 * 
 * Centralized service for all n8n webhook integrations
 * Handles meal plan updates, notifications, and other automated workflows
 */
class WebhookService
{
    /**
     * Default timeout for webhook requests (seconds)
     */
    private const DEFAULT_TIMEOUT = 10;

    /**
     * Trigger n8n webhook when meal plan is updated
     * Used by WF3: Shopping List Auto-Update
     * 
     * @param int $weekId
     * @param int $householdId
     * @return bool
     */
    public function triggerMealPlanUpdated($weekId, $householdId)
    {
        $webhookUrl = env('N8N_WEBHOOK_URL');
        
        if (!$webhookUrl) {
            Log::warning('N8N_WEBHOOK_URL not configured. Skipping meal plan webhook trigger.');
            return false;
        }

        return $this->sendWebhook($webhookUrl, [
            'event' => 'meal_plan_updated',
            'week_id' => $weekId,
            'household_id' => $householdId,
            'timestamp' => now()->toIso8601String(),
        ], 'meal_plan');
    }

    /**
     * Send notification via n8n webhook
     * Used for generic notifications (email, telegram, slack)
     * 
     * @param array $data Notification data
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendNotification(array $data)
    {
        $webhookUrl = env('N8N_NOTIFICATION_WEBHOOK_URL');
        
        if (!$webhookUrl) {
            Log::error('N8N_NOTIFICATION_WEBHOOK_URL not configured');
            return [
                'success' => false,
                'message' => 'n8n webhook URL not configured'
            ];
        }

        $success = $this->sendWebhook($webhookUrl, $data, 'notification');
        
        return [
            'success' => $success,
            'message' => $success ? 'Notification sent successfully' : 'Failed to send notification'
        ];
    }

    /**
     * Send expiring items email notification
     * Used by frontend to trigger email via n8n
     * 
     * @param array $emailData Email data including user, items, recipients
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendExpiringItemsEmail(array $emailData)
    {
        $webhookUrl = env('N8N_NOTIFICATION_WEBHOOK_URL');
        
        if (!$webhookUrl) {
            Log::error('N8N_NOTIFICATION_WEBHOOK_URL not configured for expiring items email');
            return [
                'success' => false,
                'message' => 'n8n webhook URL not configured'
            ];
        }

        $payload = array_merge([
            'event' => 'expiring_items_email',
            'timestamp' => now()->toIso8601String(),
        ], $emailData);

        $success = $this->sendWebhook($webhookUrl, $payload, 'expiring_items_email');
        
        return [
            'success' => $success,
            'message' => $success ? 'Email sent successfully' : 'Failed to send email'
        ];
    }

    /**
     * Generic webhook sender
     * 
     * @param string $url Webhook URL
     * @param array $data Payload data
     * @param string $type Webhook type (for logging)
     * @return bool
     */
    private function sendWebhook(string $url, array $data, string $type = 'generic'): bool
    {
        try {
            $response = Http::timeout(self::DEFAULT_TIMEOUT)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'HomeLife-API/1.0',
                ])
                ->post($url, $data);

            if ($response->successful()) {
                Log::info("n8n webhook triggered successfully: {$type}", [
                    'type' => $type,
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return true;
            } else {
                Log::warning("n8n webhook failed: {$type}", [
                    'type' => $type,
                    'url' => $url,
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'data' => $data,
                ]);
                return false;
            }
        } catch (ConnectionException $e) {
            Log::error("n8n webhook connection error: {$type}", [
                'type' => $type,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error("n8n webhook error: {$type}", [
                'type' => $type,
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Validate webhook secret (for incoming webhooks from n8n)
     * 
     * @param string|null $providedSecret
     * @return bool
     */
    public function validateWebhookSecret(?string $providedSecret): bool
    {
        $expectedSecret = env('N8N_WEBHOOK_SECRET');
        
        if (!$expectedSecret) {
            return env('APP_ENV') !== 'production';
        }

        return hash_equals($expectedSecret, $providedSecret ?? '');
    }
}

