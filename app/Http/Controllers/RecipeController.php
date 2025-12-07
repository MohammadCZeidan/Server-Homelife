<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Services\RecipeService;
use App\Services\IngredientService;
use App\Services\UnitService;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\Inventory;

class RecipeController extends Controller
{
    private RecipeService $recipeService;
<<<<<<< HEAD
    private IngredientService $ingredientService;
    private UnitService $unitService;

    public function __construct(RecipeService $recipeService, IngredientService $ingredientService, UnitService $unitService)
=======
    private AIService $aiService;
    private IngredientService $ingredientService;
    private UnitService $unitService;

    public function __construct(RecipeService $recipeService, AIService $aiService, IngredientService $ingredientService, UnitService $unitService)
>>>>>>> 1a3e34bd8fe77bbd575e8a222cb42d55f1a808d3
    {
        $this->recipeService = $recipeService;
        $this->ingredientService = $ingredientService;
        $this->unitService = $unitService;
    }

<<<<<<< HEAD
    /**
     * Helper function to find or create a unit by abbreviation
     */
=======
>>>>>>> 1a3e34bd8fe77bbd575e8a222cb42d55f1a808d3
    private function findOrCreateUnit(string $unitAbbreviation): Unit
    {
        $unit = Unit::where('abbreviation', $unitAbbreviation)
            ->orWhere('name', $unitAbbreviation)
            ->first();
        
        if (!$unit) {
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

    public function getAll(Request $request): JsonResponse
    {
        $user = Auth::user();
        $recipes = $this->recipeService->getAll($user->household_id);
        return $this->responseJSON($recipes);
    }

    public function get($id): JsonResponse
    {
        $user = Auth::user();
        $recipe = $this->recipeService->get($id, $user->household_id);
        
        if (!$recipe) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($recipe);
    }

    public function create(Request $request): JsonResponse
    {
        $user = Auth::user();

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
                    return $this->responseJSON(null, "failure", 422);
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
                    return $this->responseJSON(null, "failure", 422);
                }

                // Verify unit exists (should always be true since we just created it if needed)
                $unitExists = Unit::where('id', $unitId)->exists();
                if (!$unitExists) {
                    return $this->responseJSON(null, "failure", 422);
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
<<<<<<< HEAD
        } catch (\Exception $e) {
            \Log::error('Recipe creation failed: ' . $e->getMessage());
=======
        } catch (Exception $e) {
            Log::error('Recipe creation failed: ' . $e->getMessage());
>>>>>>> 1a3e34bd8fe77bbd575e8a222cb42d55f1a808d3
            return $this->responseJSON(null, "failure", 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

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
                    return $this->responseJSON(null, "failure", 422);
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
                    return $this->responseJSON(null, "failure", 422);
                }

                // Verify unit exists (should always be true since we just created it if needed)
                $unitExists = Unit::where('id', $unitId)->exists();
                if (!$unitExists) {
                    return $this->responseJSON(null, "failure", 422);
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

<<<<<<< HEAD
    public function delete($id): JsonResponse
=======
    public function delete($id): JsonResponse // Deletes a recipe (must belong to user's household)
>>>>>>> 1a3e34bd8fe77bbd575e8a222cb42d55f1a808d3
    {
        $user = Auth::user();
        $deleted = $this->recipeService->delete($id, $user->household_id);
        
        if (!$deleted) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON(null, "success");
    }

    public function getSuggestionsFromPantry(Request $request): JsonResponse
    {
        $user = Auth::user();
        $limit = $request->get('limit', 5);
        $recipes = $this->recipeService->getSuggestionsFromPantry($user->household_id, $limit);
        return $this->responseJSON($recipes);
    }

    public function getSubstitutions(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
<<<<<<< HEAD
=======

>>>>>>> 1a3e34bd8fe77bbd575e8a222cb42d55f1a808d3
        $recipe = $this->recipeService->get($id, $user->household_id);
        
        if (!$recipe) {
            return $this->responseJSON([], "failure", 404);
        }

        $recipeIngredientIds = $recipe->ingredients->pluck('id')->toArray();
<<<<<<< HEAD
=======
        
>>>>>>> 1a3e34bd8fe77bbd575e8a222cb42d55f1a808d3
        $pantryIngredientIds = Inventory::where('household_id', $user->household_id)
            ->where('quantity', '>', 0)
            ->pluck('ingredient_id')
            ->unique()
            ->toArray();

        $missingIngredientIds = array_diff($recipeIngredientIds, $pantryIngredientIds);
        $substitutions = [];
        
        foreach ($missingIngredientIds as $missingId) {
<<<<<<< HEAD
            $ingredient = Ingredient::where('household_id', $user->household_id)->find($missingId);
            if ($ingredient) {
=======
            $sub = $this->aiService->getSmartSubstitutions($missingId, $user->household_id);
            if (!empty($sub)) {
                $ingredient = Ingredient::where('household_id', $user->household_id)->find($missingId);
>>>>>>> 1a3e34bd8fe77bbd575e8a222cb42d55f1a808d3
                $substitutions[] = [
                    'missing_ingredient' => $ingredient->name,
                    'substitution' => 'No substitution found',
                ];
            }
        }

        return $this->responseJSON($substitutions);
    }
}

