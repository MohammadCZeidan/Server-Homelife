<?php

namespace App\Services;

use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\Week;
use App\Models\Meal;
use App\Models\Inventory;

class ShoppingListService
{
    function getAll($householdId)
    {
        return ShoppingList::with(['items.ingredient', 'items.unit', 'week'])
            ->where('household_id', $householdId)
            ->get();
    }

    function get($id, $householdId)
    {
        return ShoppingList::with(['items.ingredient', 'items.unit', 'week'])
            ->where('id', $id)
            ->where('household_id', $householdId)
            ->first();
    }

    function create($householdId, $title, $weekId = null)
    {
        $list = new ShoppingList;
        $list->title = $title;
        $list->household_id = $householdId;
        $list->week_id = $weekId;
        $list->save();

        $list->load(['items.ingredient', 'items.unit', 'week']);
        return $list;
    }

    function update($id, $householdId, $data)
    {
        $list = ShoppingList::where('id', $id)
            ->where('household_id', $householdId)
            ->first();

        if (!$list) {
            return null;
        }

        if (isset($data['title'])) {
            $list->title = $data['title'];
        }
        if (isset($data['is_completed'])) {
            $list->is_completed = $data['is_completed'];
        }
        $list->save();

        $list->load(['items.ingredient', 'items.unit', 'week']);
        return $list;
    }

    function delete($id, $householdId)
    {
        $list = ShoppingList::where('id', $id)
            ->where('household_id', $householdId)
            ->first();

        if (!$list) {
            return false;
        }

        return $list->delete();
    }

    function addItem($listId, $householdId, $ingredientId, $quantity, $unitId)
    {
        $list = ShoppingList::where('id', $listId)
            ->where('household_id', $householdId)
            ->first();

        if (!$list) {
            return null;
        }

        $item = new ShoppingListItem;
        $item->shopping_list_id = $listId;
        $item->ingredient_id = $ingredientId;
        $item->quantity = $quantity;
        $item->unit_id = $unitId;
        $item->save();

        $item->load(['ingredient', 'unit']);
        return $item;
    }

    function updateItem($listId, $householdId, $itemId, $data)
    {
        $list = ShoppingList::where('id', $listId)
            ->where('household_id', $householdId)
            ->first();

        if (!$list) {
            return null;
        }

        $item = ShoppingListItem::where('id', $itemId)
            ->where('shopping_list_id', $listId)
            ->first();

        if (!$item) {
            return null;
        }

        if (isset($data['quantity'])) {
            $item->quantity = $data['quantity'];
        }
        if (isset($data['bought'])) {
            $item->bought = $data['bought'];
        }
        $item->save();

        $item->load(['ingredient', 'unit']);
        return $item;
    }

    function deleteItem($listId, $householdId, $itemId)
    {
        $list = ShoppingList::where('id', $listId)
            ->where('household_id', $householdId)
            ->first();

        if (!$list) {
            return false;
        }

        $item = ShoppingListItem::where('id', $itemId)
            ->where('shopping_list_id', $listId)
            ->first();

        if (!$item) {
            return false;
        }

        return $item->delete();
    }

    function generateFromMealPlan($householdId, $weekId, $title = null)
    {
        $week = Week::find($weekId);
        if (!$week || $week->household_id != $householdId) {
            return null;
        }

        $list = new ShoppingList;
        $list->title = $title ?? 'Shopping List - ' . $week->start_date;
        $list->household_id = $householdId;
        $list->week_id = $weekId;
        $list->save();

        $meals = Meal::with('recipe.ingredients')->where('week_id', $weekId)->get();
        $requiredIngredients = [];

        foreach ($meals as $meal) {
            if ($meal->recipe && $meal->recipe->ingredients) {
                foreach ($meal->recipe->ingredients as $ingredient) {
                    $ingredientId = $ingredient->id;
                    $pivot = $ingredient->pivot;

                    if (!isset($requiredIngredients[$ingredientId])) {
                        $requiredIngredients[$ingredientId] = [
                            'ingredient_id' => $ingredientId,
                            'quantity' => 0,
                            'unit_id' => $pivot->unit_id,
                        ];
                    }
                    $requiredIngredients[$ingredientId]['quantity'] += $pivot->quantity;
                }
            }
        }

        $pantry = Inventory::where('household_id', $householdId)->get();
        $pantryByIngredient = [];
        foreach ($pantry as $item) {
            $ingredientId = $item->ingredient_id;
            if (!isset($pantryByIngredient[$ingredientId])) {
                $pantryByIngredient[$ingredientId] = 0;
            }
            $pantryByIngredient[$ingredientId] += $item->quantity;
        }

        foreach ($requiredIngredients as $ingredientId => $data) {
            $needed = $data['quantity'];
            $available = $pantryByIngredient[$ingredientId] ?? 0;

            if ($needed > $available) {
                $item = new ShoppingListItem;
                $item->shopping_list_id = $list->id;
                $item->ingredient_id = $ingredientId;
                $item->quantity = $needed - $available;
                $item->unit_id = $data['unit_id'];
                $item->save();
            }
        }

        $list->load(['items.ingredient', 'items.unit', 'week']);
        return $list;
    }

    function regenerateItemsFromMealPlan($listId, $householdId, $weekId)
    {
        $list = ShoppingList::where('id', $listId)
            ->where('household_id', $householdId)
            ->first();

        if (!$list) {
            return null;
        }

        $week = Week::find($weekId);
        if (!$week || $week->household_id != $householdId) {
            return null;
        }

        $list->items()->delete();

        $meals = Meal::with('recipe.ingredients')->where('week_id', $weekId)->get();
        $requiredIngredients = [];

        foreach ($meals as $meal) {
            if ($meal->recipe && $meal->recipe->ingredients) {
                foreach ($meal->recipe->ingredients as $ingredient) {
                    $ingredientId = $ingredient->id;
                    $pivot = $ingredient->pivot;

                    if (!isset($requiredIngredients[$ingredientId])) {
                        $requiredIngredients[$ingredientId] = [
                            'ingredient_id' => $ingredientId,
                            'quantity' => 0,
                            'unit_id' => $pivot->unit_id,
                        ];
                    }
                    $requiredIngredients[$ingredientId]['quantity'] += $pivot->quantity;
                }
            }
        }

        $pantry = Inventory::where('household_id', $householdId)->get();
        $pantryByIngredient = [];
        foreach ($pantry as $item) {
            $ingredientId = $item->ingredient_id;
            if (!isset($pantryByIngredient[$ingredientId])) {
                $pantryByIngredient[$ingredientId] = 0;
            }
            $pantryByIngredient[$ingredientId] += $item->quantity;
        }

        foreach ($requiredIngredients as $ingredientId => $data) {
            $needed = $data['quantity'];
            $available = $pantryByIngredient[$ingredientId] ?? 0;

            if ($needed > $available) {
                $item = new ShoppingListItem;
                $item->shopping_list_id = $list->id;
                $item->ingredient_id = $ingredientId;
                $item->quantity = $needed - $available;
                $item->unit_id = $data['unit_id'];
                $item->save();
            }
        }

        $list->load(['items.ingredient', 'items.unit', 'week']);
        return $list;
    }
}

