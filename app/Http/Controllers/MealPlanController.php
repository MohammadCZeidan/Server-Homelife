<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\MealPlanService;
use App\Models\Recipe;

class MealPlanController extends Controller
{
    private MealPlanService $mealPlanService;

    public function __construct(MealPlanService $mealPlanService)
    {
        $this->mealPlanService = $mealPlanService;
    }

    public function getWeeklyPlan(Request $request): JsonResponse
    {
        $user = Auth::user();

        $weekStartDate = $request->get('weekStartDate') ?? $request->get('start_date');
        $week = $this->mealPlanService->getWeeklyPlan($user->household_id, $weekStartDate);
        
        if (!$week) {
            return $this->responseJSON([
                'id' => null,
                'start_date' => $weekStartDate,
                'end_date' => null,
                'meals' => []
            ], "success");
        }

        return $this->responseJSON($week);
    }

    public function createWeeklyPlan(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
        ]);

        $user = Auth::user();
        $week = $this->mealPlanService->createWeeklyPlan($user->household_id, $request->start_date);
        return $this->responseJSON($week);
    }

    public function addMeal(Request $request, $weekId): JsonResponse
    {
        $slot = $request->input('slot') ?? $request->input('meal_type');
        
        $day = $request->input('day');
        $dayValue = $day;
        if (is_string($day)) {
            $dayMap = [
                'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                'thursday' => 4, 'friday' => 5, 'saturday' => 6
            ];
            $dayValue = isset($dayMap[strtolower($day)]) ? $dayMap[strtolower($day)] : (int)$day;
        }

        $user = Auth::user();

        if (!$slot) {
            return $this->responseJSON(null, "failure", 422);
        }

        $recipe = Recipe::where('id', $request->recipe_id)
            ->where('household_id', $user->household_id)
            ->first();

        if (!$recipe && $request->recipe_id) {
            return $this->responseJSON(null, "failure", 422);
        }

        $request->validate([
            'day' => 'required',
            'recipe_id' => 'required',
        ], [
            'day.required' => 'Day is required (0-6 or day name like "monday")',
            'recipe_id.required' => 'Recipe ID is required',
        ]);

        if (!in_array($slot, ['breakfast', 'lunch', 'dinner', 'snack'])) {
            return $this->responseJSON(null, "failure", 422);
        }

        $finalDay = (int)$dayValue;
        if ($finalDay < 0 || $finalDay > 6) {
            return $this->responseJSON(null, "failure", 422);
        }

        $meal = $this->mealPlanService->addMeal(
            $weekId,
            $user->household_id,
            $finalDay,
            $slot,
            $request->recipe_id
        );
        
        if (!$meal) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($meal);
    }

    public function removeMeal($weekId, $mealId): JsonResponse
    {
        $user = Auth::user();
        $deleted = $this->mealPlanService->removeMeal($weekId, $user->household_id, $mealId);
        
        if (!$deleted) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON(null, "success");
    }
}

