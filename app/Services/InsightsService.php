<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Week;
use App\Models\Meal;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InsightsService
{
    function getWeeklyInsights($householdId, $weekStartDate = null)
    {
        $dateRange = $this->getWeekDateRange($weekStartDate);
        $spending = $this->getSpendingData($householdId, $dateRange['start'], $dateRange['end']);
        $waste = $this->getWasteData($householdId, $dateRange['start']);
        $planning = $this->getPlanningData($householdId, $dateRange['start']);
        $expiringSoon = $this->getExpiringSoonData($householdId);

        return [
            'week' => [
                'start_date' => $dateRange['start']->toDateString(),
                'end_date' => $dateRange['end']->toDateString(),
            ],
            'spending' => $spending,
            'waste' => $waste,
            'planning' => $planning,
            'expiring_soon' => $expiringSoon,
            'ai_summary' => $this->generateAISummary($householdId, $spending['total'], $waste['count'], $planning['meals_planned'], $expiringSoon),
        ];
    }

    private function getWeekDateRange($weekStartDate = null)
    {
        if (!$weekStartDate) {
            $weekStartDate = Carbon::now()->startOfWeek()->toDateString();
        }

        $startDate = Carbon::parse($weekStartDate)->startOfWeek();
        $endDate = $startDate->copy()->endOfWeek();

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    private function getSpendingData($householdId, $startDate, $endDate)
    {
        $expenses = Expense::where('household_id', $householdId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $totalSpend = $expenses->sum('amount');
        $expenseCount = $expenses->count();
        $byCategory = $expenses->groupBy('category')->map(function ($items) {
            return $items->sum('amount');
        });

        return [
            'total' => round($totalSpend, 2),
            'count' => $expenseCount,
            'average_per_transaction' => $expenseCount > 0 ? round($totalSpend / $expenseCount, 2) : 0,
            'by_category' => $byCategory,
        ];
    }

    private function getWasteData($householdId, $startDate)
    {
        $expiredItems = Inventory::with('ingredient')
            ->where('household_id', $householdId)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $startDate)
            ->where('expiry_date', '>=', $startDate->copy()->subWeek())
            ->get();

        $wasteItems = $expiredItems->map(function ($item) {
            return [
                'ingredient' => $item->ingredient->name,
                'quantity' => $item->quantity,
                'expiry_date' => $item->expiry_date,
            ];
        });

        return [
            'count' => $expiredItems->count(),
            'items' => $wasteItems,
        ];
    }

    private function getPlanningData($householdId, $startDate)
    {
        $week = Week::with('meals')->where('household_id', $householdId)
            ->where('start_date', $startDate->toDateString())
            ->first();

        $mealsPlanned = 0;
        $mealsBySlot = [
            'breakfast' => 0,
            'lunch' => 0,
            'dinner' => 0,
            'snack' => 0,
        ];

        if ($week && $week->meals) {
            $mealsPlanned = $week->meals->count();
            foreach ($week->meals as $meal) {
                $mealsBySlot[$meal->slot] = ($mealsBySlot[$meal->slot] ?? 0) + 1;
            }
        }

        return [
            'meals_planned' => $mealsPlanned,
            'by_slot' => $mealsBySlot,
            'coverage' => round(($mealsPlanned / 21) * 100, 1),
        ];
    }

    private function getExpiringSoonData($householdId)
    {
        $expiringSoon = Inventory::with('ingredient')
            ->where('household_id', $householdId)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', Carbon::now())
            ->where('expiry_date', '<=', Carbon::now()->addDays(7))
            ->orderBy('expiry_date', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'ingredient' => $item->ingredient->name,
                    'quantity' => $item->quantity,
                    'expiry_date' => $item->expiry_date,
                    'days_until_expiry' => Carbon::now()->diffInDays($item->expiry_date, false),
                ];
            });

        return $expiringSoon;
    }

    private function generateAISummary($householdId, $totalSpend, $wasteCount, $mealsPlanned, $expiringSoon)
    {
        $apiKey = env('OPENAI_API_KEY');
        
        if (!$apiKey) {
            return null;
        }

        $expiringCount = $expiringSoon->count();
        $expiringItems = $expiringSoon->take(5)->pluck('ingredient')->implode(', ');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful household food management assistant. Provide friendly, actionable weekly insights.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Generate a brief weekly summary (2-3 sentences) for a household that:\n- Spent \${$totalSpend} on groceries\n- Wasted {$wasteCount} expired items\n- Planned {$mealsPlanned} meals\n- Has {$expiringCount} items expiring soon: {$expiringItems}\n\nMake it friendly, encouraging, and include one actionable tip.",
                    ],
                ],
                'max_tokens' => 150,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $summary = $response->json()['choices'][0]['message']['content'];
                return trim($summary);
            }
        } catch (Exception $e) {
            Log::error('AI Insights Summary Error: ' . $e->getMessage());
        }

        return null;
    }
}
