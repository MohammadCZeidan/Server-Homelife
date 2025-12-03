<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Services\PantryService;
use App\Models\Inventory;

class PantryController extends Controller
{
    private PantryService $pantryService;

    public function __construct(PantryService $pantryService)
    {
        $this->pantryService = $pantryService;
    }

    public function getAll(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $inventory = $this->pantryService->getAll($user->household_id);
        return $this->responseJSON($inventory);
    }

    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0',
            'unit_id' => 'required|exists:units,id',
            'expiry_date' => 'nullable|date',
            'location' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $inventory = $this->pantryService->create($user->household_id, $request->all());
        return $this->responseJSON($inventory);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        $inventory = Inventory::where('id', $id)
            ->where('household_id', $user->household_id)
            ->first();

        if (!$inventory) {
            return $this->responseJSON(null, "failure", 404);
        }

        $ingredientName = $request->input('ingredient_name') ?? $request->input('name');
        $validationRules = [
            'quantity' => 'nullable|numeric|min:0',
            'unit_id' => 'nullable|exists:units,id',
            'expiry_date' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'ingredient_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'calories' => 'nullable|numeric|min:0',
            'protein' => 'nullable|numeric|min:0',
            'carbs' => 'nullable|numeric|min:0',
            'fat' => 'nullable|numeric|min:0',
        ];

        if ($ingredientName) {
            $validationRules['ingredient_name'] = [
                'nullable',
                'string',
                'max:255',
                Rule::unique('ingredients', 'name')
                    ->where('household_id', $user->household_id)
                    ->ignore($inventory->ingredient_id)
            ];
            $validationRules['name'] = [
                'nullable',
                'string',
                'max:255',
                Rule::unique('ingredients', 'name')
                    ->where('household_id', $user->household_id)
                    ->ignore($inventory->ingredient_id)
            ];
        }

        $request->validate($validationRules);

        $inventory = $this->pantryService->update($id, $user->household_id, $request->all());
        
        if (!$inventory) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($inventory);
    }

    public function delete(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        if (!is_numeric($id)) {
            return $this->responseJSON(null, "failure", 400);
        }

        $deleted = $this->pantryService->delete($id, $user->household_id);
        
        if (!$deleted) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON(null, "success");
    }

    public function consume(Request $request, $id): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|numeric|min:0',
        ]);

        $user = Auth::user();
        $result = $this->pantryService->consume($id, $user->household_id, $request->quantity);
        
        if (!$result) {
            return $this->responseJSON(null, "failure", 404);
        }

        if ($result['deleted']) {
            return $this->responseJSON(null, "success");
        }

        return $this->responseJSON($result['inventory']);
    }

    public function getExpiringSoon(Request $request): JsonResponse
    {
        $user = Auth::user();
        $days = (int) $request->get('days', 7);
        $inventory = $this->pantryService->getExpiringSoon($user->household_id, $days);
        
        $items = $inventory->map(function ($item) {
            if (!$item->expiry_date) {
                return $item;
            }
            
            $expiryDate = Carbon::parse($item->expiry_date);
            $now = Carbon::now();
            $daysUntil = $now->diffInDays($expiryDate, false);
            
            $item->use_first = $daysUntil <= 2 && $daysUntil >= 0;
            $item->days_until_expiry = $daysUntil;
            $item->expiry_date = $expiryDate->format('Y-m-d');
            
            return $item;
        });
        
        return $this->responseJSON($items);
    }
    
    public function updateExpiryDate(Request $request, $id): JsonResponse
    {
        $request->validate([
            'expiry_date' => 'required|date',
        ]);

        $user = Auth::user();
        $inventory = $this->pantryService->update($id, $user->household_id, [
            'expiry_date' => $request->expiry_date
        ]);
        
        if (!$inventory) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($inventory);
    }

    public function mergeDuplicates(): JsonResponse
    {
        $user = Auth::user();
        $result = $this->pantryService->mergeDuplicates($user->household_id);
        return $this->responseJSON($result, "success");
    }
}

