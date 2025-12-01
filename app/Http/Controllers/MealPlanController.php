<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\MealPlanService;

class MealPlanController extends Controller
{
    private $mealPlanService;

    function __construct(MealPlanService $mealPlanService)
    {
        $this->mealPlanService = $mealPlanService;
    }

    function getWeeklyPlan(Request $request)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON(null, "failure", 404);
        }

        // Accept both 'weekStartDate' and 'start_date' query parameters
        $weekStartDate = $request->get('weekStartDate') ?? $request->get('start_date');
        $week = $this->mealPlanService->getWeeklyPlan($user->household_id, $weekStartDate);
        
        // Return empty week structure instead of 404 if week doesn't exist
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

    function createWeeklyPlan(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
        ]);

        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON(null, "failure", 404);
        }

        $week = $this->mealPlanService->createWeeklyPlan($user->household_id, $request->start_date);
        return $this->responseJSON($week);
    }

    function addMeal(Request $request, $weekId)
    {
        // Accept both 'slot' and 'meal_type' field names
        $slot = $request->input('slot') ?? $request->input('meal_type');
        
        // Handle day as integer or string (e.g., "monday" -> 1)
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
        if (!$user->household_id) {
            return $this->responseJSON(null, "failure", 400);
        }

        // Custom validation: ensure at least one of slot or meal_type is provided
        if (!$slot) {
            return response()->json([
                'status' => 'failure',
                'payload' => null,
                'message' => 'Either "slot" or "meal_type" is required. Must be: breakfast, lunch, dinner, or snack'
            ], 422);
        }

        // Validate recipe exists and belongs to household
        $recipe = \App\Models\Recipe::where('id', $request->recipe_id)
            ->where('household_id', $user->household_id)
            ->first();

        if (!$recipe && $request->recipe_id) {
            return response()->json([
                'status' => 'failure',
                'payload' => null,
                'message' => 'The selected recipe does not exist or does not belong to your household'
            ], 422);
        }

        $request->validate([
            'day' => 'required',
            'recipe_id' => 'required',
        ], [
            'day.required' => 'Day is required (0-6 or day name like "monday")',
            'recipe_id.required' => 'Recipe ID is required',
        ]);

        // Validate slot value
        if (!in_array($slot, ['breakfast', 'lunch', 'dinner', 'snack'])) {
            return response()->json([
                'status' => 'failure',
                'payload' => null,
                'message' => 'Slot/meal_type must be one of: breakfast, lunch, dinner, snack'
            ], 422);
        }

        // Validate day value (0-6)
        $finalDay = (int)$dayValue;
        if ($finalDay < 0 || $finalDay > 6) {
            return response()->json([
                'status' => 'failure',
                'payload' => null,
                'message' => 'Day must be between 0-6 (0=Sunday, 1=Monday, ..., 6=Saturday)'
            ], 422);
        }

        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON(null, "failure", 400);
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

    function removeMeal($weekId, $mealId)
    {
        $user = Auth::user();
        $deleted = $this->mealPlanService->removeMeal($weekId, $user->household_id, $mealId);
        
        if (!$deleted) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON(null, "success");
    }
}

