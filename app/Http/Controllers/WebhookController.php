<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ShoppingListService;

class WebhookController extends Controller
{
    private $shoppingListService;

    function __construct(ShoppingListService $shoppingListService)
    {
        $this->shoppingListService = $shoppingListService;
    }

    function mealPlanUpdated(Request $request)
    {
        // Validate webhook secret for security
        $webhookSecret = env('N8N_WEBHOOK_SECRET');
        if ($webhookSecret) {
            $providedSecret = $request->header('X-Webhook-Secret') ?? $request->input('secret');
            if ($providedSecret !== $webhookSecret) {
                return response()->json([
                    'status' => 'failure',
                    'payload' => null,
                    'message' => 'Invalid webhook secret'
                ], 401);
            }
        }

        $request->validate([
            'week_id' => 'required|exists:weeks,id',
        ]);

        $week = \App\Models\Week::find($request->week_id);
        if (!$week) {
            return $this->responseJSON(null, "failure", 404);
        }

        $shoppingList = \App\Models\ShoppingList::where('week_id', $week->id)->first();
        
        if ($shoppingList) {
            $this->shoppingListService->regenerateItemsFromMealPlan(
                $shoppingList->id,
                $week->household_id,
                $week->id
            );
            $shoppingList->refresh();
            $shoppingList->load(['items.ingredient', 'items.unit']);
        }

        return $this->responseJSON([
            'message' => 'Shopping list updated',
            'week_id' => $week->id,
            'shopping_list_id' => $shoppingList->id ?? null,
        ]);
    }
}

