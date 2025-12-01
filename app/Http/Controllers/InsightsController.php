<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\InsightsService;

class InsightsController extends Controller
{
    private $insightsService;

    function __construct(InsightsService $insightsService)
    {
        $this->insightsService = $insightsService;
    }

    function getWeeklyInsights(Request $request)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON(null, "failure", 404);
        }

        $weekStartDate = $request->get('weekStartDate');
        $insights = $this->insightsService->getWeeklyInsights($user->household_id, $weekStartDate);
        return $this->responseJSON($insights);
    }
}

