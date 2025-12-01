<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\PantryController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\MealPlanController;
use App\Http\Controllers\ShoppingListController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\NutritionController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\N8nController;
use App\Http\Controllers\N8nNotificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API Version 0.1
Route::prefix('v0.1')->group(function () {
    // Public authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // Protected authentication routes
    Route::prefix('auth')->middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/profile/update', [AuthController::class, 'updateProfile']);
    });

    // Admin routes - Get all users with roles
    Route::prefix('users')->middleware(['auth:api', 'admin.only'])->group(function () {
        Route::get('/', [AuthController::class, 'getAllUsers']);
    });

    // Protected household routes
    Route::prefix('household')->middleware('auth:api')->group(function () {
        Route::get('/', [HouseholdController::class, 'get']);
        Route::post('/', [HouseholdController::class, 'create']);
        Route::post('/join', [HouseholdController::class, 'join']);
        Route::post('/invite', [HouseholdController::class, 'generateInvite']);
    });

    // Protected pantry routes
    Route::prefix('pantry')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::get('/', [PantryController::class, 'getAll']);
        Route::post('/', [PantryController::class, 'create']);
        Route::post('/{id}/update', [PantryController::class, 'update']);
        Route::post('/{id}/expiry', [PantryController::class, 'updateExpiryDate']); // Update expiry date only
        Route::delete('/{id}', [PantryController::class, 'delete']); // RESTful DELETE method
        Route::post('/{id}/delete', [PantryController::class, 'delete']); // Keep POST for backward compatibility
        Route::post('/{id}/consume', [PantryController::class, 'consume']);
        Route::get('/expiring', [PantryController::class, 'getExpiringSoon']);
        Route::post('/merge-duplicates', [PantryController::class, 'mergeDuplicates']);
    });

    // Protected recipe routes
    Route::prefix('recipes')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::get('/', [RecipeController::class, 'getAll']);
        Route::get('/suggestions', [RecipeController::class, 'getSuggestionsFromPantry']); // Must come before /{id}
        Route::get('/{id}', [RecipeController::class, 'get']);
        Route::post('/', [RecipeController::class, 'create']);
        Route::post('/{id}/update', [RecipeController::class, 'update']);
        Route::post('/{id}/delete', [RecipeController::class, 'delete']);
        Route::get('/{id}/substitutions', [RecipeController::class, 'getSubstitutions']);
    });

    // Protected meal plan routes
    Route::prefix('meal-plans')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::get('/', [MealPlanController::class, 'getWeeklyPlan']);
        Route::post('/', [MealPlanController::class, 'createWeeklyPlan']);
        Route::post('/{weekId}/meals', [MealPlanController::class, 'addMeal']);
        Route::post('/{weekId}/meals/{mealId}/delete', [MealPlanController::class, 'removeMeal']);
    });

    // Protected shopping list routes
    Route::prefix('shopping-lists')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::get('/', [ShoppingListController::class, 'getAll']);
        Route::get('/{id}', [ShoppingListController::class, 'get']);
        Route::post('/', [ShoppingListController::class, 'create']);
        Route::post('/{id}/update', [ShoppingListController::class, 'update']);
        Route::post('/{id}/delete', [ShoppingListController::class, 'delete']);
        Route::post('/{id}/items', [ShoppingListController::class, 'addItem']);
        Route::post('/{id}/items/{itemId}/update', [ShoppingListController::class, 'updateItem']);
        Route::post('/{id}/items/{itemId}/delete', [ShoppingListController::class, 'deleteItem']);
        Route::post('/generate', [ShoppingListController::class, 'generateFromMealPlan']);
    });

    // Protected expense routes
    Route::prefix('expenses')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::get('/', [ExpenseController::class, 'getAll']);
        Route::get('/{id}', [ExpenseController::class, 'get']);
        Route::post('/', [ExpenseController::class, 'create']);
        Route::post('/{id}/update', [ExpenseController::class, 'update']);
        Route::post('/{id}/delete', [ExpenseController::class, 'delete']);
        Route::get('/summary', [ExpenseController::class, 'getSummary']);
    });

    // Protected ingredient routes
    Route::prefix('ingredients')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::get('/', [IngredientController::class, 'getAll']);
        Route::get('/{id}', [IngredientController::class, 'get']);
        Route::post('/', [IngredientController::class, 'create']);
    });

    // Protected unit routes
    Route::prefix('units')->middleware('auth:api')->group(function () {
        Route::get('/', [UnitController::class, 'getAll']);
        Route::post('/', [UnitController::class, 'create']);
    });

    // Protected nutrition routes
    Route::prefix('nutrition')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::get('/recipes/{recipeId}', [NutritionController::class, 'getRecipeNutrition']);
        Route::get('/weeks/{weekId}', [NutritionController::class, 'getWeeklyNutrition']);
    });

    // Protected insights routes
    Route::prefix('insights')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::get('/weekly', [InsightsController::class, 'getWeeklyInsights']);
    });

    // Protected AI routes
    Route::prefix('ai')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::post('/generate-seed-data', [AIController::class, 'generateSeedData']);
        Route::get('/recipe-suggestions', [AIController::class, 'getRecipeSuggestionsFromPantry']);
        Route::get('/substitutions/{ingredientId}', [AIController::class, 'getSmartSubstitutions']);
    });

    // Webhook routes (for n8n - no auth required, but should use webhook secret)
    Route::prefix('webhooks')->group(function () {
        Route::post('/meal-plan-updated', [WebhookController::class, 'mealPlanUpdated']);
    });

    // N8N Integration routes (use API key authentication instead of JWT)
    Route::prefix('n8n')->group(function () {
        // WF1: Daily Expiry Alerts
        Route::get('/households/{householdId}/pantry/expiring', [N8nController::class, 'getExpiringItems']);
        
        // WF2: Weekly Meal Plan Draft
        Route::get('/households/{householdId}/pantry', [N8nController::class, 'getPantryItems']);
        Route::get('/households/{householdId}/recipes', [N8nController::class, 'getRecipes']);
        Route::post('/households/{householdId}/meal-plans', [N8nController::class, 'createMealPlan']);
        Route::post('/households/{householdId}/meal-plans/{weekId}/meals', [N8nController::class, 'addMealToPlan']);
        
        // Get household users (for notifications)
        Route::get('/households/{householdId}/users', [N8nController::class, 'getHouseholdUsers']);
        
        // Notification endpoint
        Route::post('/send-notification', [N8nNotificationController::class, 'sendNotification']);
    });
});

