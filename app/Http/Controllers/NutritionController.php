<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\NutritionService;

class NutritionController extends Controller
{
    private NutritionService $nutritionService;

    public function __construct(NutritionService $nutritionService)
    {
        $this->nutritionService = $nutritionService;
    }

    public function getRecipeNutrition($recipeId): JsonResponse
    {
        $user = Auth::user();
        $nutrition = $this->nutritionService->getRecipeNutrition($recipeId, $user->household_id);
        
        if (!$nutrition) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($nutrition);
    }

    public function getWeeklyNutrition($weekId): JsonResponse
    {
        $user = Auth::user();
        $nutrition = $this->nutritionService->getWeeklyNutrition($weekId, $user->household_id);
        
        if (!$nutrition) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($nutrition);
    }
}

