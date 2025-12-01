<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Trigger n8n webhook when meal plan is updated
     * 
     * @param int $weekId
     * @param int $householdId
     * @return bool
     */
    public function triggerMealPlanUpdated($weekId, $householdId)
    {
        $webhookUrl = env('N8N_WEBHOOK_URL');
        
        if (!$webhookUrl) {
            Log::warning('N8N_WEBHOOK_URL not configured. Skipping webhook trigger.');
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($webhookUrl, [
                    'week_id' => $weekId,
                    'household_id' => $householdId,
                    'timestamp' => now()->toIso8601String(),
                ]);

            if ($response->successful()) {
                Log::info('Meal plan webhook triggered successfully', [
                    'week_id' => $weekId,
                    'household_id' => $householdId,
                ]);
                return true;
            } else {
                Log::warning('Meal plan webhook failed', [
                    'week_id' => $weekId,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Meal plan webhook error', [
                'week_id' => $weekId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

