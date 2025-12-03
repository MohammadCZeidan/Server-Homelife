<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\InsightsService;

class InsightsController extends Controller
{
    private InsightsService $insightsService;

    public function __construct(InsightsService $insightsService)
    {
        $this->insightsService = $insightsService;
    }

    public function getWeeklyInsights(Request $request): JsonResponse
    {
        $user = Auth::user();
        $weekStartDate = $request->get('weekStartDate');
        $insights = $this->insightsService->getWeeklyInsights($user->household_id, $weekStartDate);
        return $this->responseJSON($insights);
    }
}

