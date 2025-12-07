<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
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
        $user = Auth::user();
        $search = $request->get('search');
        $ingredients = $this->ingredientService->getAll($user->household_id, $search);
        return $this->responseJSON($ingredients);
    }

    public function get($id): JsonResponse
    {
        $user = Auth::user();
        $ingredient = $this->ingredientService->get($id, $user->household_id);
        if (!$ingredient) {
            return $this->responseJSON(null, "failure", 404);
        }
        return $this->responseJSON($ingredient);
    }

    public function create(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->validateRequest($request, 'create');

        $householdId = $user->household_id;

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

