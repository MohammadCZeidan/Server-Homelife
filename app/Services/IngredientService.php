<?php

namespace App\Services;

use App\Models\Ingredient;

class IngredientService
{
    function getAll($householdId, $search = null)
    {
        $query = Ingredient::with('unit')->where('household_id', $householdId);

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->orderBy('name')->get();
    }

    function get($id, $householdId)
    {
        return Ingredient::with('unit')->where('household_id', $householdId)->find($id);
    }

    function create($householdId, $data)
    {
        $ingredient = new Ingredient;
        $ingredient->name = $data['name'];
        $ingredient->calories = $data['calories'] ?? 0;
        $ingredient->protein = $data['protein'] ?? 0;
        $ingredient->carbs = $data['carbs'] ?? 0;
        $ingredient->fat = $data['fat'] ?? 0;
        $ingredient->household_id = $householdId;
        $ingredient->unit_id = $data['unit_id'] ?? null;
        $ingredient->save();

        $ingredient->load('unit');
        return $ingredient;
    }
}

