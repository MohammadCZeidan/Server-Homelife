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
use App\Http\Controllers\AIController;

// All API routes are versioned under v0.1
Route::prefix('v0.1')->group(function () {
    // Anyone can register or login without authentication
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // These routes require a valid JWT token
    Route::prefix('auth')->middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/profile/update', [AuthController::class, 'updateProfile']);
    });

    // Only admins can access these routes
    Route::prefix('users')->middleware(['auth:api', 'admin.only'])->group(function () {
        Route::get('/', [AuthController::class, 'getAllUsers']);
    });

    // User must be logged in to manage their household
    Route::prefix('household')->middleware('auth:api')->group(function () {
        Route::get('/', [HouseholdController::class, 'get']);
        Route::post('/', [HouseholdController::class, 'create']);
        Route::post('/join', [HouseholdController::class, 'join']);
        Route::post('/invite', [HouseholdController::class, 'generateInvite']);
    });

    // User needs to be logged in and have a household to access pantry
    Route::prefix('pantry')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::get('/', [PantryController::class, 'getAll']);
        Route::post('/', [PantryController::class, 'create']);
        Route::post('/{id}/update', [PantryController::class, 'update']);
        Route::post('/{id}/expiry', [PantryController::class, 'updateExpiryDate']); // Just updates the expiry date, nothing else
        Route::delete('/{id}', [PantryController::class, 'delete']); // Using proper RESTful DELETE method
        Route::post('/{id}/delete', [PantryController::class, 'delete']); // Keeping this for older clients that still use POST
        Route::post('/{id}/consume', [PantryController::class, 'consume']);
        Route::get('/expiring', [PantryController::class, 'getExpiringSoon']);
        Route::post('/merge-duplicates', [PantryController::class, 'mergeDuplicates']);
    });

    // Recipes require authentication and household membership
    Route::prefix('recipes')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::get('/', [RecipeController::class, 'getAll']);
        Route::get('/suggestions', [RecipeController::class, 'getSuggestionsFromPantry']); // This needs to be before /{id} or Laravel will think "suggestions" is an ID
        Route::get('/{id}', [RecipeController::class, 'get']);
        Route::post('/', [RecipeController::class, 'create']);
        Route::post('/{id}/update', [RecipeController::class, 'update']);
        Route::post('/{id}/delete', [RecipeController::class, 'delete']);
        Route::get('/{id}/substitutions', [RecipeController::class, 'getSubstitutions']);
    });

    // Meal planning features
    Route::prefix('meal-plans')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::get('/', [MealPlanController::class, 'getWeeklyPlan']);
        Route::post('/', [MealPlanController::class, 'createWeeklyPlan']);
        Route::post('/{weekId}/meals', [MealPlanController::class, 'addMeal']);
        Route::post('/{weekId}/meals/{mealId}/delete', [MealPlanController::class, 'removeMeal']);
    });

    // Shopping list management
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

    // Expense tracking
    Route::prefix('expenses')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::get('/', [ExpenseController::class, 'getAll']);
        Route::get('/{id}', [ExpenseController::class, 'get']);
        Route::post('/', [ExpenseController::class, 'create']);
        Route::post('/{id}/update', [ExpenseController::class, 'update']);
        Route::post('/{id}/delete', [ExpenseController::class, 'delete']);
        Route::get('/summary', [ExpenseController::class, 'getSummary']);
    });

    // Ingredients are public - no login needed
    Route::prefix('ingredients')->group(function () {
        Route::get('/', [IngredientController::class, 'getAll']);
        Route::get('/{id}', [IngredientController::class, 'get']);
        Route::post('/', [IngredientController::class, 'create']);
    });

    // Units require authentication
    Route::prefix('units')->middleware('auth:api')->group(function () {
        Route::get('/', [UnitController::class, 'getAll']);
        Route::post('/', [UnitController::class, 'create']);
    });

    // Nutrition calculations
    Route::prefix('nutrition')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::get('/recipes/{recipeId}', [NutritionController::class, 'getRecipeNutrition']);
        Route::get('/weeks/{weekId}', [NutritionController::class, 'getWeeklyNutrition']);
    });

    // Weekly insights and analytics
    Route::prefix('insights')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::get('/weekly', [InsightsController::class, 'getWeeklyInsights']);
    });

    // AI-powered features
    Route::prefix('ai')->middleware(['auth:api', 'household.required'])->group(function () {
        Route::post('/generate-seed-data', [AIController::class, 'generateSeedData']);
        Route::get('/recipe-suggestions', [AIController::class, 'getRecipeSuggestionsFromPantry']);
        Route::get('/substitutions/{ingredientId}', [AIController::class, 'getSmartSubstitutions']);
    });

});

