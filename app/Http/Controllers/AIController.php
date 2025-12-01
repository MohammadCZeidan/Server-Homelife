<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AIService;
use App\Services\IngredientService;
use App\Services\UnitService;
use App\Services\RecipeService;
use App\Services\PantryService;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\Inventory;

class AIController extends Controller
{
    private $aiService;
    private $ingredientService;
    private $unitService;
    private $recipeService;
    private $pantryService;

    function __construct(
        AIService $aiService,
        IngredientService $ingredientService,
        UnitService $unitService,
        RecipeService $recipeService,
        PantryService $pantryService
    ) {
        $this->aiService = $aiService;
        $this->ingredientService = $ingredientService;
        $this->unitService = $unitService;
        $this->recipeService = $recipeService;
        $this->pantryService = $pantryService;
    }

    /**
     * Generate seed data using AI
     */
    function generateSeedData(Request $request)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON(null, "failure", 404);
        }

        $householdId = $user->household_id;

        // Get AI-generated seed data
        $seedData = $this->aiService->generateSeedData($householdId);

        $created = [
            'ingredients' => 0,
            'recipes' => 0,
            'pantry_items' => 0,
        ];

        // Create ingredients with nutrition
        foreach ($seedData['ingredients'] ?? [] as $ingredientData) {
            try {
                // Find or create unit
                $unit = Unit::where('abbreviation', $ingredientData['unit'] ?? 'g')
                    ->orWhere('name', ucfirst($ingredientData['unit'] ?? 'g'))//returns first letter as an uppercase
                    ->first();

                if (!$unit) {
                    $unit = $this->unitService->create([
                        'name' => ucfirst($ingredientData['unit'] ?? 'Gram'),
                        'abbreviation' => $ingredientData['unit'] ?? 'g',
                    ]);
                }

                // Check if ingredient already exists
                $existing = Ingredient::where('name', $ingredientData['name'])
                    ->where('household_id', $householdId)
                    ->first();

                if (!$existing) {
                    $this->ingredientService->create($householdId, [
                        'name' => $ingredientData['name'],
                        'calories' => $ingredientData['calories'] ?? 0,
                        'protein' => $ingredientData['protein'] ?? 0,
                        'carbs' => $ingredientData['carbs'] ?? 0,
                        'fat' => $ingredientData['fat'] ?? 0,
                        'unit_id' => $unit->id,
                    ]);
                    $created['ingredients']++;
                }
            } catch (\Exception $e) {
                \Log::error('Failed to create ingredient: ' . $e->getMessage());
            }
        }

        // Create recipes
        foreach ($seedData['recipes'] ?? [] as $recipeData) {
            try {
                // Process ingredients
                $processedIngredients = [];
                foreach ($recipeData['ingredients'] ?? [] as $ing) {
                    $ingredient = Ingredient::where('name', $ing['name'])
                        ->where('household_id', $householdId)
                        ->first();

                    if ($ingredient) {
                        $unit = Unit::where('abbreviation', $ing['unit'] ?? 'g')
                            ->orWhere('name', ucfirst($ing['unit'] ?? 'g'))
                            ->first();

                        if (!$unit) {
                            $unit = $this->unitService->create([
                                'name' => ucfirst($ing['unit'] ?? 'Gram'),
                                'abbreviation' => $ing['unit'] ?? 'g',
                            ]);
                        }

                        $processedIngredients[] = [
                            'ingredient_id' => $ingredient->id,
                            'quantity' => $ing['amount'] ?? 0,
                            'unit_id' => $unit->id,
                        ];
                    }
                }

                // Check if recipe already exists
                $existing = \App\Models\Recipe::where('title', $recipeData['title'])
                    ->where('household_id', $householdId)
                    ->first();

                if (!$existing && !empty($processedIngredients)) {
                    $this->recipeService->create($householdId, [
                        'title' => $recipeData['title'],
                        'instructions' => $recipeData['instructions'] ?? '',
                        'tags' => $recipeData['tags'] ?? [],
                        'servings' => $recipeData['servings'] ?? 4,
                        'prep_time' => $recipeData['prep_time'] ?? 0,
                        'cook_time' => $recipeData['cook_time'] ?? 0,
                        'ingredients' => $processedIngredients,
                    ]);
                    $created['recipes']++;
                }
            } catch (\Exception $e) {
                \Log::error('Failed to create recipe: ' . $e->getMessage());
            }
        }

        // Add some ingredients to pantry
        $ingredients = Ingredient::where('household_id', $householdId)->limit(10)->get();
        foreach ($ingredients as $ingredient) {
            try {
                $this->pantryService->create($householdId, [
                    'ingredient_id' => $ingredient->id,
                    'quantity' => rand(100, 500),
                    'unit_id' => $ingredient->unit_id,
                    'expiry_date' => now()->addDays(rand(3, 14))->toDateString(),
                    'location' => 'pantry',
                ]);
                $created['pantry_items']++;
            } catch (\Exception $e) {
                \Log::error('Failed to create pantry item: ' . $e->getMessage());
            }
        }

        return $this->responseJSON([
            'message' => 'Seed data generated successfully',
            'created' => $created,
        ]);
    }

    /**
     * Get recipe suggestions from pantry (enhanced with full recipe details)
     */
    function getRecipeSuggestionsFromPantry(Request $request)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON([], "failure", 404);
        }

        $limit = $request->get('limit', 5);
        $useAI = $request->get('use_ai', true);

        if ($useAI) {
            $suggestions = $this->aiService->getRecipeSuggestionsFromPantry($user->household_id, $limit);
            return $this->responseJSON(['suggestions' => $suggestions, 'source' => 'ai']);
        }

        $recipes = \App\Services\RecipeService::getSuggestionsFromPantry($user->household_id, $limit);
        return $this->responseJSON($recipes);
    }

    /**
     * Get smart substitutions for missing ingredients
     */
    function getSmartSubstitutions(Request $request, $ingredientId)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON([], "failure", 404);
        }

        $substitution = $this->aiService->getSmartSubstitutions($ingredientId, $user->household_id);
        return $this->responseJSON($substitution);
    }
}

