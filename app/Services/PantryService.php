<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Ingredient;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class PantryService
{
    function getAll($householdId)
    {
        return Inventory::with(['ingredient', 'unit'])
            ->where('household_id', $householdId)
            ->get();
    }

    function create($householdId, $data)
    {
        $existing = Inventory::where('household_id', $householdId)
            ->where('ingredient_id', $data['ingredient_id'])
            ->where('unit_id', $data['unit_id'])
            ->where('expiry_date', $data['expiry_date'] ?? null)
            ->where('location', $data['location'] ?? null)
            ->first();

        if ($existing) {
            $existing->quantity += $data['quantity'];
            $existing->save();
            $existing->load(['ingredient', 'unit']);
            return $existing;
        }

        $inventory = new Inventory;
        $inventory->ingredient_id = $data['ingredient_id'];
        $inventory->quantity = $data['quantity'];
        $inventory->unit_id = $data['unit_id'];
        $inventory->expiry_date = $data['expiry_date'] ?? null;
        $inventory->location = $data['location'] ?? null;
        $inventory->household_id = $householdId;
        $inventory->save();

        $inventory->load(['ingredient', 'unit']);
        return $inventory;
    }

    function update($id, $householdId, $data)
    {
        $inventory = Inventory::where('id', $id)
            ->where('household_id', $householdId)
            ->first();

        if (!$inventory) {
            return null;
        }

        if (isset($data['ingredient_name']) || isset($data['name']) || 
            isset($data['calories']) || isset($data['protein']) || 
            isset($data['carbs']) || isset($data['fat'])) {
            
            $ingredient = Ingredient::where('id', $inventory->ingredient_id)
                ->where('household_id', $householdId)
                ->first();

            if ($ingredient) {
                if (isset($data['ingredient_name'])) {
                    $ingredient->name = $data['ingredient_name'];
                } elseif (isset($data['name'])) {
                    $ingredient->name = $data['name'];
                }
                if (isset($data['calories'])) {
                    $ingredient->calories = $data['calories'];
                }
                if (isset($data['protein'])) {
                    $ingredient->protein = $data['protein'];
                }
                if (isset($data['carbs'])) {
                    $ingredient->carbs = $data['carbs'];
                }
                if (isset($data['fat'])) {
                    $ingredient->fat = $data['fat'];
                }
                $ingredient->save();
            }
        }

        if (isset($data['quantity'])) {
            $inventory->quantity = $data['quantity'];
        }
        if (isset($data['unit_id'])) {
            $inventory->unit_id = $data['unit_id'];
        }
        if (isset($data['expiry_date'])) {
            $inventory->expiry_date = $data['expiry_date'];
        }
        if (isset($data['location'])) {
            $inventory->location = $data['location'];
        }

        $inventory->save();
        $inventory->load(['ingredient', 'unit']);
        return $inventory;
    }

    function delete($id, $householdId)
    {
        $inventory = Inventory::where('id', $id)
            ->where('household_id', $householdId)
            ->first();

        if (!$inventory) {
            return false;
        }

        try {
            $deleted = $inventory->delete();
            return $deleted;
        } catch (Exception $e) {
            Log::error('Delete inventory error: ' . $e->getMessage());
            return false;
        }
    }

    function consume($id, $householdId, $quantity)
    {
        $inventory = Inventory::where('id', $id)
            ->where('household_id', $householdId)
            ->first();

        if (!$inventory) {
            return null;
        }

        $inventory->quantity -= $quantity;
        if ($inventory->quantity <= 0) {
            $inventory->delete();
            return ['deleted' => true];
        }

        $inventory->save();
        $inventory->load(['ingredient', 'unit']);
        return ['deleted' => false, 'inventory' => $inventory];
    }

    function getExpiringSoon($householdId, $days = 7)
    {
        $now = Carbon::now()->startOfDay();
        $expiryDate = Carbon::now()->addDays($days)->endOfDay();
        
        return Inventory::with(['ingredient', 'unit'])
            ->where('household_id', $householdId)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $expiryDate)
            ->where('expiry_date', '>=', $now)
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date', 'asc')
            ->get();
    }

    function mergeDuplicates($householdId)
    {
        $items = Inventory::where('household_id', $householdId)
            ->orderBy('ingredient_id')
            ->orderBy('expiry_date')
            ->orderBy('location')
            ->orderBy('unit_id')
            ->get();

        $merged = [];
        $toDelete = [];

        foreach ($items as $item) {
            $key = sprintf(
                '%s_%s_%s_%s',
                $item->ingredient_id,
                $item->expiry_date ?? 'null',
                $item->location ?? 'null',
                $item->unit_id
            );

            if (!isset($merged[$key])) {
                $merged[$key] = $item;
            } else {
                $merged[$key]->quantity += $item->quantity;
                $merged[$key]->save();
                $toDelete[] = $item->id;
            }
        }

        if (!empty($toDelete)) {
            Inventory::whereIn('id', $toDelete)->delete();
        }

        return [
            'merged' => count($merged),
            'deleted' => count($toDelete)
        ];
    }
}
