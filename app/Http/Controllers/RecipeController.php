<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\RecipeService;
use App\Services\AIService;
use App\Services\IngredientService;
use App\Services\UnitService;
use App\Models\Ingredient;
use App\Models\Unit;

class RecipeController extends Controller
{
    private $recipeService;
    private $aiService;
    private $ingredientService;
    private $unitService;

    function __construct(RecipeService $recipeService, AIService $aiService, IngredientService $ingredientService, UnitService $unitService)
    {
        $this->recipeService = $recipeService;
        $this->aiService = $aiService;
        $this->ingredientService = $ingredientService;
        $this->unitService = $unitService;
    }

    /**
     * Helper function to find or create a unit by abbreviation
     */
    private function findOrCreateUnit($unitAbbreviation)
    {
        $unit = Unit::where('abbreviation', $unitAbbreviation)
            ->orWhere('name', $unitAbbreviation)
            ->first();
        
        if (!$unit) {
            // Create unit if it doesn't exist
            $unitNameMap = [
                'g' => 'Gram',
                'kg' => 'Kilogram',
                'L' => 'Liter',
                'mL' => 'Milliliter',
                'ml' => 'Milliliter',
                'cup' => 'Cup',
                'pieces' => 'Piece',
                'piece' => 'Piece',
                'pc' => 'Piece',
                'pack' => 'Piece',
            ];
            $unitName = $unitNameMap[strtolower($unitAbbreviation)] ?? ucfirst(strtolower($unitAbbreviation));
            $unit = $this->unitService->create([
                'name' => $unitName,
                'abbreviation' => $unitAbbreviation,
            ]);
        }
        
        return $unit;
    }

    function getAll(Request $request)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON([], "failure", 404);
        }

        $recipes = $this->recipeService->getAll($user->household_id);
        return $this->responseJSON($recipes);
    }

    function get($id)
    {
        $user = Auth::user();
        $recipe = $this->recipeService->get($id, $user->household_id);
        
        if (!$recipe) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($recipe);
    }

    function create(Request $request)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON(null, "failure", 404);
        }

        // Validate basic recipe fields first
        $request->validate([
            'title' => 'required|string|max:255',
            'instructions' => 'required|string',
            'tags' => 'nullable|array',
            'servings' => 'nullable|integer|min:1',
            'prep_time' => 'nullable|integer|min:0',
            'cook_time' => 'nullable|integer|min:0',
            'ingredients' => 'nullable|array',
            'ingredients.*.quantity' => 'required|numeric|min:0',
        ]);

        // Process ingredients: accept either ingredient_id or ingredient name
        $processedIngredients = [];
        if ($request->has('ingredients') && is_array($request->ingredients)) {
            foreach ($request->ingredients as $index => $ingredient) {
                $ingredientId = null;
                
                // Accept either ingredient_id or ingredient name
                if (isset($ingredient['ingredient_id'])) {
                    $ingredientId = $ingredient['ingredient_id'];
                } elseif (isset($ingredient['ingredient']) || isset($ingredient['name'])) {
                    // Look up ingredient by name, or create it if it doesn't exist
                    $ingredientName = $ingredient['ingredient'] ?? $ingredient['name'];
                    $foundIngredient = Ingredient::where('name', $ingredientName)
                        ->where('household_id', $user->household_id)
                        ->first();
                    
                    if (!$foundIngredient) {
                        // Auto-create ingredient if it doesn't exist
                        // First, get or create the unit
                        $unitId = null;
                        if (isset($ingredient['unit_id'])) {
                            $unitId = $ingredient['unit_id'];
                        } elseif (isset($ingredient['unit'])) {
                            $unit = $this->findOrCreateUnit($ingredient['unit']);
                            $unitId = $unit->id;
                        } else {
                            // Default to 'g' (Gram) if no unit specified
                            $unit = $this->findOrCreateUnit('g');
                            $unitId = $unit->id;
                        }
                        
                        // Create the ingredient
                        $foundIngredient = $this->ingredientService->create($user->household_id, [
                            'name' => $ingredientName,
                            'unit_id' => $unitId,
                        ]);
                    }
                    $ingredientId = $foundIngredient->id;
                } else {
                    return response()->json([
                        'status' => 'failure',
                        'payload' => null,
                        'message' => "Ingredient at index {$index} must have either 'ingredient_id' or 'ingredient'/'name' field."
                    ], 422);
                }

                // Get unit_id: use provided one, or find/create by abbreviation, or fall back to ingredient's default unit_id
                $unitId = null;
                if (isset($ingredient['unit_id'])) {
                    $unitId = $ingredient['unit_id'];
                } elseif (isset($ingredient['unit'])) {
                    // Try to find unit by abbreviation
                    $unit = $this->findOrCreateUnit($ingredient['unit']);
                    $unitId = $unit->id;
                } else {
                    // Try to get the ingredient's default unit_id
                    $ingredientModel = Ingredient::find($ingredientId);
                    if ($ingredientModel && $ingredientModel->unit_id) {
                        $unitId = $ingredientModel->unit_id;
                    } else {
                        // Default to 'g' (Gram) if no unit specified
                        $unit = $this->findOrCreateUnit('g');
                        $unitId = $unit->id;
                    }
                }

                // Verify ingredient belongs to household (should always be true since we just created it if needed)
                $ingredientExists = Ingredient::where('id', $ingredientId)
                    ->where('household_id', $user->household_id)
                    ->exists();

                if (!$ingredientExists) {
                    return response()->json([
                        'status' => 'failure',
                        'payload' => null,
                        'message' => "Ingredient ID {$ingredientId} does not belong to your household."
                    ], 422);
                }

                // Verify unit exists (should always be true since we just created it if needed)
                $unitExists = Unit::where('id', $unitId)->exists();
                if (!$unitExists) {
                    return response()->json([
                        'status' => 'failure',
                        'payload' => null,
                        'message' => "Unit ID {$unitId} does not exist."
                    ], 422);
                }

                $processedIngredients[] = [
                    'ingredient_id' => $ingredientId,
                    'quantity' => $ingredient['quantity'],
                    'unit_id' => $unitId,
                ];
            }
        }

        // Replace ingredients array with processed one
        $requestData = $request->all();
        $requestData['ingredients'] = $processedIngredients;

        try {
            $recipe = $this->recipeService->create($user->household_id, $requestData);
            return $this->responseJSON($recipe);
        } catch (\Exception $e) {
            \Log::error('Recipe creation failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'failure',
                'payload' => null,
                'message' => 'Failed to create recipe: ' . $e->getMessage()
            ], 500);
        }
    }

    function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON(null, "failure", 404);
        }

        $request->validate([
            'title' => 'nullable|string|max:255',
            'instructions' => 'nullable|string',
            'tags' => 'nullable|array',
            'servings' => 'nullable|integer|min:1',
            'prep_time' => 'nullable|integer|min:0',
            'cook_time' => 'nullable|integer|min:0',
            'ingredients' => 'nullable|array',
        ]);

        // Process ingredients: accept either ingredient_id or ingredient name
        $processedIngredients = [];
        if ($request->has('ingredients') && is_array($request->ingredients)) {
            foreach ($request->ingredients as $index => $ingredient) {
                $ingredientId = null;
                
                // Accept either ingredient_id or ingredient name
                if (isset($ingredient['ingredient_id'])) {
                    $ingredientId = $ingredient['ingredient_id'];
                } elseif (isset($ingredient['ingredient']) || isset($ingredient['name'])) {
                    // Look up ingredient by name, or create it if it doesn't exist
                    $ingredientName = $ingredient['ingredient'] ?? $ingredient['name'];
                    $foundIngredient = Ingredient::where('name', $ingredientName)
                        ->where('household_id', $user->household_id)
                        ->first();
                    
                    if (!$foundIngredient) {
                        // Auto-create ingredient if it doesn't exist
                        // First, get or create the unit
                        $unitId = null;
                        if (isset($ingredient['unit_id'])) {
                            $unitId = $ingredient['unit_id'];
                        } elseif (isset($ingredient['unit'])) {
                            $unit = $this->findOrCreateUnit($ingredient['unit']);
                            $unitId = $unit->id;
                        } else {
                            // Default to 'g' (Gram) if no unit specified
                            $unit = $this->findOrCreateUnit('g');
                            $unitId = $unit->id;
                        }
                        
                        // Create the ingredient
                        $foundIngredient = $this->ingredientService->create($user->household_id, [
                            'name' => $ingredientName,
                            'unit_id' => $unitId,
                        ]);
                    }
                    $ingredientId = $foundIngredient->id;
                } else {
                    return response()->json([
                        'status' => 'failure',
                        'payload' => null,
                        'message' => "Ingredient at index {$index} must have either 'ingredient_id' or 'ingredient'/'name' field."
                    ], 422);
                }

                // Get unit_id: use provided one, or find/create by abbreviation, or fall back to ingredient's default unit_id
                $unitId = null;
                if (isset($ingredient['unit_id'])) {
                    $unitId = $ingredient['unit_id'];
                } elseif (isset($ingredient['unit'])) {
                    // Try to find unit by abbreviation
                    $unit = $this->findOrCreateUnit($ingredient['unit']);
                    $unitId = $unit->id;
                } else {
                    // Try to get the ingredient's default unit_id
                    $ingredientModel = Ingredient::find($ingredientId);
                    if ($ingredientModel && $ingredientModel->unit_id) {
                        $unitId = $ingredientModel->unit_id;
                    } else {
                        // Default to 'g' (Gram) if no unit specified
                        $unit = $this->findOrCreateUnit('g');
                        $unitId = $unit->id;
                    }
                }

                // Verify ingredient belongs to household (should always be true since we just created it if needed)
                $ingredientExists = Ingredient::where('id', $ingredientId)
                    ->where('household_id', $user->household_id)
                    ->exists();

                if (!$ingredientExists) {
                    return response()->json([
                        'status' => 'failure',
                        'payload' => null,
                        'message' => "Ingredient ID {$ingredientId} does not belong to your household."
                    ], 422);
                }

                // Verify unit exists (should always be true since we just created it if needed)
                $unitExists = Unit::where('id', $unitId)->exists();
                if (!$unitExists) {
                    return response()->json([
                        'status' => 'failure',
                        'payload' => null,
                        'message' => "Unit ID {$unitId} does not exist."
                    ], 422);
                }

                $processedIngredients[] = [
                    'ingredient_id' => $ingredientId,
                    'quantity' => $ingredient['quantity'],
                    'unit_id' => $unitId,
                ];
            }
        }

        // Replace ingredients array with processed one
        $requestData = $request->all();
        if (!empty($processedIngredients)) {
            $requestData['ingredients'] = $processedIngredients;
        }

        $recipe = $this->recipeService->update($id, $user->household_id, $requestData);
        
        if (!$recipe) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($recipe);
    }

    function delete($id)
    {
        $user = Auth::user();
        $deleted = $this->recipeService->delete($id, $user->household_id);
        
        if (!$deleted) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON(null, "success");
    }

    function getSuggestionsFromPantry(Request $request)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON([], "failure", 404);
        }

        $limit = $request->get('limit', 5);
        $useAI = $request->get('use_ai', false);

        if ($useAI) {
            $suggestions = $this->aiService->getRecipeSuggestionsFromPantry($user->household_id, $limit);
            return $this->responseJSON(['suggestions' => $suggestions, 'source' => 'ai']);
        }

        $recipes = $this->recipeService->getSuggestionsFromPantry($user->household_id, $limit);
        return $this->responseJSON($recipes);
    }

    function getSubstitutions(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON([], "failure", 404);
        }

        // Get recipe and find missing ingredients
        $recipe = $this->recipeService->get($id, $user->household_id);
        if (!$recipe) {
            return $this->responseJSON([], "failure", 404);
        }

        // Get all recipe ingredients
        $recipeIngredientIds = $recipe->ingredients->pluck('id')->toArray();
        
        // Get pantry ingredients
        $pantryIngredientIds = \App\Models\Inventory::where('household_id', $user->household_id)
            ->where('quantity', '>', 0)
            ->pluck('ingredient_id')
            ->unique()
            ->toArray();

        // Find missing ingredients
        $missingIngredientIds = array_diff($recipeIngredientIds, $pantryIngredientIds);
        
        $substitutions = [];
        foreach ($missingIngredientIds as $missingId) {
            $sub = $this->aiService->getSmartSubstitutions($missingId, $user->household_id);
            if (!empty($sub)) {
                $ingredient = \App\Models\Ingredient::where('household_id', $user->household_id)->find($missingId);
                $substitutions[] = [
                    'missing_ingredient' => $ingredient ? $ingredient->name : 'Unknown',
                    'substitution' => $sub['substitution'] ?? 'No substitution found',
                ];
            }
        }

        return $this->responseJSON($substitutions);
    }
}

