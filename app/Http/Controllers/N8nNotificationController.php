<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8nNotificationController extends Controller
{
    public function sendNotification(Request $request)
    {
        $request->validate([
            'household_id' => 'required|string',
            'channels' => 'required|array',
            'channels.*' => 'in:email,telegram,slack',
            'message' => 'required|string',
            'subject' => 'nullable|string',
            'sender_email' => 'required|email',
        ]);

        $householdId = $request->input('household_id');
        $channels = $request->input('channels');
        $message = $request->input('message');
        $subject = $request->input('subject', 'HomeLife Notification');
        $senderEmail = $request->input('sender_email');

        // Get n8n webhook URL from environment
        $webhookUrl = env('N8N_NOTIFICATION_WEBHOOK_URL');
        
        if (!$webhookUrl) {
            return response()->json([
                'success' => false,
                'message' => 'n8n webhook URL not configured'
            ], 500);
        }

        try {
            // Trigger n8n webhook with notification data
            $response = Http::post($webhookUrl, [
                'household_id' => $householdId,
                'channels' => $channels,
                'message' => $message,
                'subject' => $subject,
                'sender_email' => $senderEmail,
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Notification sent successfully'
                ]);
            } else {
                Log::error('n8n webhook failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send notification'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('n8n notification error', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error sending notification: ' . $e->getMessage()
            ], 500);
        }
    }
}

