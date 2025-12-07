<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\WebhookService;

class N8nNotificationController extends Controller
{
    private WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Send notification via n8n
     * Generic endpoint for sending notifications through n8n workflows
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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

        $notificationData = [
            'household_id' => $request->input('household_id'),
            'channels' => $request->input('channels'),
            'message' => $request->input('message'),
            'subject' => $request->input('subject', 'HomeLife Notification'),
            'sender_email' => $request->input('sender_email'),
        ];

        $result = $this->webhookService->sendNotification($notificationData);

        return response()->json($result, $result['success'] ? 200 : 500);
    }
}

