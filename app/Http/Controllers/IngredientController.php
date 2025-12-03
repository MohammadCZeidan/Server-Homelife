<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\IngredientService;
use App\Models\Ingredient;

class IngredientController extends Controller
{
    private IngredientService $ingredientService;

    public function __construct(IngredientService $ingredientService)
    {
        $this->ingredientService = $ingredientService;
    }

    public function getAll(Request $request): JsonResponse
    {
        $householdId = $request->get('household_id');
        $search = $request->get('search');
        
        if (!$householdId) {
            $ingredients = \App\Models\Ingredient::with('unit')
                ->when($search, function ($query, $search) {
                    return $query->where('name', 'like', "%{$search}%");
                })
                ->get();
        } else {
            $ingredients = $this->ingredientService->getAll($householdId, $search);
        }
        
        return $this->responseJSON($ingredients);
    }

    public function get($id): JsonResponse
    {
        $ingredient = \App\Models\Ingredient::with('unit')->find($id);
        if (!$ingredient) {
            return $this->responseJSON(null, "failure", 404);
        }
        return $this->responseJSON($ingredient);
    }

    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'household_id' => 'required|exists:households,id',
            'calories' => 'nullable|numeric|min:0',
            'protein' => 'nullable|numeric|min:0',
            'carbs' => 'nullable|numeric|min:0',
            'fat' => 'nullable|numeric|min:0',
            'unit_id' => 'nullable|exists:units,id',
        ]);

        $householdId = $request->household_id;

        $existingIngredient = Ingredient::where('name', $request->name)
            ->where('household_id', $householdId)
            ->first();

        if ($existingIngredient) {
            if ($request->has('unit_id') && $request->unit_id != $existingIngredient->unit_id) {
                $existingIngredient->unit_id = $request->unit_id;
                $existingIngredient->save();
            }
            
            $existingIngredient->load('unit');
            return $this->responseJSON($existingIngredient);
        }

        $ingredient = $this->ingredientService->create($householdId, $request->all());
        return $this->responseJSON($ingredient);
    }
}

