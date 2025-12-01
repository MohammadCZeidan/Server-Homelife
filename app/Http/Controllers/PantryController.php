<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\PantryService;

class PantryController extends Controller
{
    private $pantryService;

    function __construct(PantryService $pantryService)
    {
        $this->pantryService = $pantryService;
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

        $inventory = $this->pantryService->getAll($user->household_id);
        return $this->responseJSON($inventory);
    }

    function create(Request $request)
    {
        $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0',
            'unit_id' => 'required|exists:units,id',
            'expiry_date' => 'nullable|date',
            'location' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        if (!$user->household_id) {
            return response()->json([
                "status" => "failure",
                "payload" => null,
                "message" => "You must create or join a household first. Use POST /api/v0.1/household to create one."
            ], 400);
        }

        $inventory = $this->pantryService->create($user->household_id, $request->all());
        return $this->responseJSON($inventory);
    }

    function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return response()->json([
                "status" => "failure",
                "payload" => null,
                "message" => "You must create or join a household first. Use POST /api/v0.1/household to create one."
            ], 400);
        }

        // Get the inventory item to access its ingredient_id
        $inventory = \App\Models\Inventory::where('id', $id)
            ->where('household_id', $user->household_id)
            ->first();

        if (!$inventory) {
            return $this->responseJSON(null, "failure", 404);
        }

        // Validate with unique ingredient name check (ignore current ingredient)
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

        // If ingredient name is being updated, validate uniqueness
        if ($ingredientName) {
            $validationRules['ingredient_name'] = [
                'nullable',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('ingredients', 'name')
                    ->where('household_id', $user->household_id)
                    ->ignore($inventory->ingredient_id)
            ];
            $validationRules['name'] = [
                'nullable',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('ingredients', 'name')
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

    function delete(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$user->household_id) {
            return response()->json([
                "status" => "failure",
                "payload" => null,
                "message" => "You must create or join a household first. Use POST /api/v0.1/household to create one."
            ], 400);
        }

        // Validate that ID is numeric
        if (!is_numeric($id)) {
            return $this->responseJSON(null, "failure", 400);
        }

        $deleted = $this->pantryService->delete($id, $user->household_id);
        
        if (!$deleted) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON(null, "success");
    }

    function consume(Request $request, $id)
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

    function getExpiringSoon(Request $request)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return response()->json([
                "status" => "failure",
                "payload" => null,
                "message" => "You must create or join a household first. Use POST /api/v0.1/household to create one."
            ], 400);
        }

        $days = (int) $request->get('days', 7);
        $inventory = $this->pantryService->getExpiringSoon($user->household_id, $days);
        
        // Add "use first" badge logic (items expiring in 1-2 days get priority)
        $items = $inventory->map(function ($item) {
            if (!$item->expiry_date) {
                return $item;
            }
            
            $expiryDate = \Carbon\Carbon::parse($item->expiry_date);
            $now = \Carbon\Carbon::now();
            $daysUntil = $now->diffInDays($expiryDate, false);
            
            $item->use_first = $daysUntil <= 2 && $daysUntil >= 0;
            $item->days_until_expiry = $daysUntil;
            $item->expiry_date = $expiryDate->format('Y-m-d');
            
            return $item;
        });
        
        return $this->responseJSON($items);
    }
    
    function updateExpiryDate(Request $request, $id)
    {
        $request->validate([
            'expiry_date' => 'required|date',
        ]);

        $user = Auth::user();
        if (!$user->household_id) {
            return response()->json([
                "status" => "failure",
                "payload" => null,
                "message" => "You must create or join a household first."
            ], 400);
        }

        $inventory = $this->pantryService->update($id, $user->household_id, [
            'expiry_date' => $request->expiry_date
        ]);
        
        if (!$inventory) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($inventory);
    }

    function mergeDuplicates()
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return response()->json([
                "status" => "failure",
                "payload" => null,
                "message" => "You must create or join a household first. Use POST /api/v0.1/household to create one."
            ], 400);
        }

        $result = $this->pantryService->mergeDuplicates($user->household_id);
        return $this->responseJSON($result, "success");
    }
}

