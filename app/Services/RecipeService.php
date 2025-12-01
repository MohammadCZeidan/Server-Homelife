<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\Inventory;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Services\PantryService;
use App\Services\ShoppingListService;

class RecipeService
{
    private $pantryService;
    private $shoppingListService;

    function __construct(PantryService $pantryService, ShoppingListService $shoppingListService)
    {
        $this->pantryService = $pantryService;
        $this->shoppingListService = $shoppingListService;
    }

    function getAll($householdId)
    {
        $recipes = Recipe::with('ingredients')
            ->where('household_id', $householdId)
            ->get();
        
        // Format ingredients to include name and unit name
        return $recipes->map(function ($recipe) {
            $recipe->ingredients = $recipe->ingredients->map(function ($ingredient) {
                $unit = Unit::find($ingredient->pivot->unit_id);
                return [
                    'id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'ingredient_name' => $ingredient->name, // Alias for easier access
                    'quantity' => $ingredient->pivot->quantity,
                    'unit_id' => $ingredient->pivot->unit_id,
                    'unit_name' => $unit ? $unit->name : null, // Direct unit name
                    'unit_abbreviation' => $unit ? $unit->abbreviation : null, // Direct unit abbreviation
                    'unit' => $unit ? [
                        'id' => $unit->id,
                        'name' => $unit->name,
                        'abbreviation' => $unit->abbreviation,
                    ] : null,
                ];
            });
            return $recipe;
        });
    }

    function get($id, $householdId)
    {
        $recipe = Recipe::with(['ingredients', 'household'])
            ->where('id', $id)
            ->where('household_id', $householdId)
            ->first();
        
        if ($recipe) {
            // Format ingredients to include name and unit name
            $recipe->ingredients = $recipe->ingredients->map(function ($ingredient) {
                $unit = Unit::find($ingredient->pivot->unit_id);
                return [
                    'id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'ingredient_name' => $ingredient->name, // Alias for easier access
                    'quantity' => $ingredient->pivot->quantity,
                    'unit_id' => $ingredient->pivot->unit_id,
                    'unit_name' => $unit ? $unit->name : null, // Direct unit name
                    'unit_abbreviation' => $unit ? $unit->abbreviation : null, // Direct unit abbreviation
                    'unit' => $unit ? [
                        'id' => $unit->id,
                        'name' => $unit->name,
                        'abbreviation' => $unit->abbreviation,
                    ] : null,
                ];
            });
        }
        
        return $recipe;
    }

    function create($householdId, $data)
    {
        try {
            $recipe = new Recipe;
            $recipe->title = $data['title'];
            $recipe->instructions = $data['instructions'];
            $recipe->tags = $data['tags'] ?? null;
            $recipe->servings = $data['servings'] ?? null;
            $recipe->prep_time = $data['prep_time'] ?? null;
            $recipe->cook_time = $data['cook_time'] ?? null;
            $recipe->household_id = $householdId;
            $recipe->save();

            if (isset($data['ingredients']) && is_array($data['ingredients'])) {
                foreach ($data['ingredients'] as $ingredientData) {
                    // Verify ingredient belongs to household before attaching
                    $ingredient = Ingredient::where('id', $ingredientData['ingredient_id'])
                        ->where('household_id', $householdId)
                        ->first();

                    if ($ingredient) {
                        // Attach ingredient to recipe
                        $recipe->ingredients()->attach($ingredientData['ingredient_id'], [
                            'quantity' => $ingredientData['quantity'],
                            'unit_id' => $ingredientData['unit_id'],
                        ]);

                        // Automatically add ingredient to pantry/inventory
                        try {
                            $this->pantryService->create($householdId, [
                                'ingredient_id' => $ingredientData['ingredient_id'],
                                'quantity' => $ingredientData['quantity'],
                                'unit_id' => $ingredientData['unit_id'],
                                'expiry_date' => null,
                                'location' => null,
                            ]);
                        } catch (\Exception $e) {
                            \Log::warning('Failed to add ingredient to pantry when creating recipe: ' . $e->getMessage());
                        }

                        // Automatically add ingredient to shopping list
                        try {
                            // Get or create an active shopping list for the household
                            $shoppingList = ShoppingList::where('household_id', $householdId)
                                ->where('is_completed', false)
                                ->orderBy('created_at', 'desc')
                                ->first();

                            if (!$shoppingList) {
                                // Create a new shopping list if none exists
                                $shoppingList = $this->shoppingListService->create($householdId, 'Shopping List - ' . date('Y-m-d'));
                            }

                            // Check if item already exists in shopping list (same ingredient and unit)
                            $existingItem = ShoppingListItem::where('shopping_list_id', $shoppingList->id)
                                ->where('ingredient_id', $ingredientData['ingredient_id'])
                                ->where('unit_id', $ingredientData['unit_id'])
                                ->first();

                            if ($existingItem) {
                                // Merge quantities if duplicate exists
                                $existingItem->quantity += $ingredientData['quantity'];
                                $existingItem->save();
                            } else {
                                // Add new item to shopping list
                                $this->shoppingListService->addItem(
                                    $shoppingList->id,
                                    $householdId,
                                    $ingredientData['ingredient_id'],
                                    $ingredientData['quantity'],
                                    $ingredientData['unit_id']
                                );
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Failed to add ingredient to shopping list when creating recipe: ' . $e->getMessage());
                        }
                    }
                }
            }

            $recipe->load('ingredients');
            
            // Format ingredients to include name and unit name
            $recipe->ingredients = $recipe->ingredients->map(function ($ingredient) {
                $unit = Unit::find($ingredient->pivot->unit_id);
                return [
                    'id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'ingredient_name' => $ingredient->name, // Alias for easier access
                    'quantity' => $ingredient->pivot->quantity,
                    'unit_id' => $ingredient->pivot->unit_id,
                    'unit_name' => $unit ? $unit->name : null, // Direct unit name
                    'unit_abbreviation' => $unit ? $unit->abbreviation : null, // Direct unit abbreviation
                    'unit' => $unit ? [
                        'id' => $unit->id,
                        'name' => $unit->name,
                        'abbreviation' => $unit->abbreviation,
                    ] : null,
                ];
            });
            
            return $recipe;
        } catch (\Exception $e) {
            \Log::error('Recipe creation error: ' . $e->getMessage());
            throw $e;
        }
    }

    function update($id, $householdId, $data)
    {
        $recipe = Recipe::where('id', $id)
            ->where('household_id', $householdId)
            ->first();

        if (!$recipe) {
            return null;
        }

        if (isset($data['title'])) {
            $recipe->title = $data['title'];
        }
        if (isset($data['instructions'])) {
            $recipe->instructions = $data['instructions'];
        }
        if (isset($data['tags'])) {
            $recipe->tags = $data['tags'];
        }
        if (isset($data['servings'])) {
            $recipe->servings = $data['servings'];
        }
        if (isset($data['prep_time'])) {
            $recipe->prep_time = $data['prep_time'];
        }
        if (isset($data['cook_time'])) {
            $recipe->cook_time = $data['cook_time'];
        }
        $recipe->save();

        if (isset($data['ingredients'])) {
            $recipe->ingredients()->detach();
            foreach ($data['ingredients'] as $ingredientData) {
                $recipe->ingredients()->attach($ingredientData['ingredient_id'], [
                    'quantity' => $ingredientData['quantity'],
                    'unit_id' => $ingredientData['unit_id'],
                ]);
            }
        }

        $recipe->load('ingredients');
        
        // Format ingredients to include name and unit name
        $recipe->ingredients = $recipe->ingredients->map(function ($ingredient) {
            $unit = Unit::find($ingredient->pivot->unit_id);
            return [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'ingredient_name' => $ingredient->name, // Alias for easier access
                'quantity' => $ingredient->pivot->quantity,
                'unit_id' => $ingredient->pivot->unit_id,
                'unit_name' => $unit ? $unit->name : null, // Direct unit name
                'unit_abbreviation' => $unit ? $unit->abbreviation : null, // Direct unit abbreviation
                'unit' => $unit ? [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'abbreviation' => $unit->abbreviation,
                ] : null,
            ];
        });
        
        return $recipe;
    }

    function delete($id, $householdId)
    {
        $recipe = Recipe::where('id', $id)
            ->where('household_id', $householdId)
            ->first();

        if (!$recipe) {
            return false;
        }

        return $recipe->delete();
    }

    function getSuggestionsFromPantry($householdId, $limit = 5)
    {
        $pantryIngredientIds = Inventory::where('household_id', $householdId)
            ->pluck('ingredient_id')
            ->unique()
            ->toArray();

        return Recipe::with('ingredients')
            ->where('household_id', $householdId)
            ->whereHas('ingredients', function ($query) use ($pantryIngredientIds) {
                $query->whereIn('ingredients.id', $pantryIngredientIds);
            })
            ->limit($limit)
            ->get();
    }
}

