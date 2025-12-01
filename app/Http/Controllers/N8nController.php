<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\PantryService;
use App\Services\RecipeService;
use App\Services\MealPlanService;
use App\Models\User;

/**
 * N8N Integration Controller
 * 
 * Special endpoints for n8n workflows that use service account authentication
 * These endpoints bypass normal user authentication and use API key instead
 */
class N8nController extends Controller
{
    private $pantryService;
    private $recipeService;
    private $mealPlanService;

    function __construct(
        PantryService $pantryService,
        RecipeService $recipeService,
        MealPlanService $mealPlanService
    ) {
        $this->pantryService = $pantryService;
        $this->recipeService = $recipeService;
        $this->mealPlanService = $mealPlanService;
    }

    /**
     * Get expiring pantry items for a household
     * Used by WF1: Daily Expiry Alerts
     * 
     * @param Request $request
     * @param int $householdId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getExpiringItems(Request $request, $householdId)
    {
        // Validate API key
        if (!$this->validateApiKey($request)) {
            return response()->json([
                'status' => 'failure',
                'payload' => null,
                'message' => 'Invalid or missing API key'
            ], 401);
        }

        $days = (int) $request->get('days', 7);
        $inventory = $this->pantryService->getExpiringSoon($householdId, $days);
        
        return $this->responseJSON($inventory);
    }

    /**
     * Get all pantry items for a household
     * Used by WF2: Weekly Meal Plan Draft
     * 
     * @param Request $request
     * @param int $householdId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPantryItems(Request $request, $householdId)
    {
        // Validate API key
        if (!$this->validateApiKey($request)) {
            return response()->json([
                'status' => 'failure',
                'payload' => null,
                'message' => 'Invalid or missing API key'
            ], 401);
        }

        $inventory = $this->pantryService->getAll($householdId);
        return $this->responseJSON($inventory);
    }

    /**
     * Get all recipes for a household
     * Used by WF2: Weekly Meal Plan Draft
     * 
     * @param Request $request
     * @param int $householdId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecipes(Request $request, $householdId)
    {
        // Validate API key
        if (!$this->validateApiKey($request)) {
            return response()->json([
                'status' => 'failure',
                'payload' => null,
                'message' => 'Invalid or missing API key'
            ], 401);
        }

        $recipes = $this->recipeService->getAll($householdId);
        return $this->responseJSON($recipes);
    }

    /**
     * Create meal plan for a household (for n8n)
     * Used by WF2: Weekly Meal Plan Draft
     * 
     * @param Request $request
     * @param int $householdId
     * @return \Illuminate\Http\JsonResponse
     */
    public function createMealPlan(Request $request, $householdId)
    {
        // Validate API key
        if (!$this->validateApiKey($request)) {
            return response()->json([
                'status' => 'failure',
                'payload' => null,
                'message' => 'Invalid or missing API key'
            ], 401);
        }

        $request->validate([
            'start_date' => 'required|date',
        ]);

        $week = $this->mealPlanService->createWeeklyPlan($householdId, $request->start_date);
        return $this->responseJSON($week);
    }

    /**
     * Add meal to meal plan (for n8n)
     * Used by WF2: Weekly Meal Plan Draft
     * 
     * @param Request $request
     * @param int $householdId
     * @param int $weekId
     * @return \Illuminate\Http\JsonResponse
     */
    public function addMealToPlan(Request $request, $householdId, $weekId)
    {
        // Validate API key
        if (!$this->validateApiKey($request)) {
            return response()->json([
                'status' => 'failure',
                'payload' => null,
                'message' => 'Invalid or missing API key'
            ], 401);
        }

        $request->validate([
            'day' => 'required|integer|min:0|max:6',
            'slot' => 'required|in:breakfast,lunch,dinner,snack',
            'recipe_id' => 'required|exists:recipes,id',
        ]);

        $meal = $this->mealPlanService->addMeal(
            $weekId,
            $householdId,
            $request->day,
            $request->slot,
            $request->recipe_id
        );

        if (!$meal) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($meal);
    }

    /**
     * Get all users in a household (for notifications)
     * Used by WF1 and WF3 to get email addresses
     * 
     * @param Request $request
     * @param int $householdId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHouseholdUsers(Request $request, $householdId)
    {
        // Validate API key
        if (!$this->validateApiKey($request)) {
            return response()->json([
                'status' => 'failure',
                'payload' => null,
                'message' => 'Invalid or missing API key'
            ], 401);
        }

        $users = \App\Models\User::where('household_id', $householdId)
            ->select('id', 'name', 'email', 'household_id')
            ->get();

        return $this->responseJSON($users);
    }

    /**
     * Validate API key from request
     * 
     * @param Request $request
     * @return bool
     */
    private function validateApiKey(Request $request)
    {
        $apiKey = $request->header('X-API-Key') ?? $request->input('api_key');
        $validApiKey = env('N8N_API_KEY');

        if (!$validApiKey) {
            // If no API key is configured, allow access (for development)
            // In production, always require API key
            return env('APP_ENV') !== 'production';
        }

        return $apiKey === $validApiKey;
    }
}

