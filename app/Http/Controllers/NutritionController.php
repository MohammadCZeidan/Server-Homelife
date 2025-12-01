<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\NutritionService;

class NutritionController extends Controller
{
    private $nutritionService;

    function __construct(NutritionService $nutritionService)
    {
        $this->nutritionService = $nutritionService;
    }

    function getRecipeNutrition($recipeId)
    {
        $user = Auth::user();
        $nutrition = $this->nutritionService->getRecipeNutrition($recipeId, $user->household_id);
        
        if (!$nutrition) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($nutrition);
    }

    function getWeeklyNutrition($weekId)
    {
        $user = Auth::user();
        $nutrition = $this->nutritionService->getWeeklyNutrition($weekId, $user->household_id);
        
        if (!$nutrition) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($nutrition);
    }
}

