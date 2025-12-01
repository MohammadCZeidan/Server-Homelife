<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\IngredientService;
use App\Models\Ingredient;

class IngredientController extends Controller
{
    private $ingredientService;

    function __construct(IngredientService $ingredientService)
    {
        $this->ingredientService = $ingredientService;
    }

    function getAll(Request $request)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return response()->json([
                "status" => "failure",
                "payload" => null,
                "message" => "You must create or join a household first. Use POST /api/v0.1/household to create one."
            ], 400);
        }

        $search = $request->get('search');
        $ingredients = $this->ingredientService->getAll($user->household_id, $search);
        return $this->responseJSON($ingredients);
    }

    function get($id)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return response()->json([
                "status" => "failure",
                "payload" => null,
                "message" => "You must create or join a household first. Use POST /api/v0.1/household to create one."
            ], 400);
        }

        $ingredient = $this->ingredientService->get($id, $user->household_id);
        if (!$ingredient) {
            return $this->responseJSON(null, "failure", 404);
        }
        return $this->responseJSON($ingredient);
    }

    function create(Request $request)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return response()->json([
                "status" => "failure",
                "payload" => null,
                "message" => "You must create or join a household first. Use POST /api/v0.1/household to create one."
            ], 400);
        }

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'calories' => 'nullable|numeric|min:0',
            'protein' => 'nullable|numeric|min:0',
            'carbs' => 'nullable|numeric|min:0',
            'fat' => 'nullable|numeric|min:0',
            'unit_id' => 'nullable|exists:units,id',
        ]);

        // Check if ingredient already exists (idempotent operation)
        $existingIngredient = Ingredient::where('name', $request->name)
            ->where('household_id', $user->household_id)
            ->first();

        if ($existingIngredient) {
            // If unit_id is provided and different, update it
            if ($request->has('unit_id') && $request->unit_id != $existingIngredient->unit_id) {
                $existingIngredient->unit_id = $request->unit_id;
                $existingIngredient->save();
            }
            
            // Load unit relationship and return existing ingredient
            $existingIngredient->load('unit');
            return $this->responseJSON($existingIngredient);
        }

        // Create new ingredient if it doesn't exist
        $ingredient = $this->ingredientService->create($user->household_id, $request->all());
        return $this->responseJSON($ingredient);
    }
}

