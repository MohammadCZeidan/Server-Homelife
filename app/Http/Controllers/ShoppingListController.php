<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ShoppingListService;

class ShoppingListController extends Controller
{
    private $shoppingListService;

    function __construct(ShoppingListService $shoppingListService)
    {
        $this->shoppingListService = $shoppingListService;
    }

    function getAll(Request $request)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON([], "failure", 404);
        }

        $lists = $this->shoppingListService->getAll($user->household_id);
        return $this->responseJSON($lists);
    }

    function get($id)
    {
        $user = Auth::user();
        $list = $this->shoppingListService->get($id, $user->household_id);
        
        if (!$list) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($list);
    }

    function create(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'week_id' => 'nullable|exists:weeks,id',
        ]);

        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON(null, "failure", 404);
        }

        $list = $this->shoppingListService->create($user->household_id, $request->title, $request->week_id);
        return $this->responseJSON($list);
    }

    function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'is_completed' => 'nullable|boolean',
        ]);

        $user = Auth::user();
        $list = $this->shoppingListService->update($id, $user->household_id, $request->all());
        
        if (!$list) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($list);
    }

    function delete($id)
    {
        $user = Auth::user();
        $deleted = $this->shoppingListService->delete($id, $user->household_id);
        
        if (!$deleted) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON(null, "success");
    }

    function addItem(Request $request, $id)
    {
        $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0',
            'unit_id' => 'required|exists:units,id',
        ]);

        $user = Auth::user();
        $item = $this->shoppingListService->addItem(
            $id,
            $user->household_id,
            $request->ingredient_id,
            $request->quantity,
            $request->unit_id
        );
        
        if (!$item) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($item);
    }

    function updateItem(Request $request, $id, $itemId)
    {
        $request->validate([
            'quantity' => 'nullable|numeric|min:0',
            'bought' => 'nullable|boolean',
        ]);

        $user = Auth::user();
        $item = $this->shoppingListService->updateItem($id, $user->household_id, $itemId, $request->all());
        
        if (!$item) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($item);
    }

    function deleteItem($id, $itemId)
    {
        $user = Auth::user();
        $deleted = $this->shoppingListService->deleteItem($id, $user->household_id, $itemId);
        
        if (!$deleted) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON(null, "success");
    }

    function generateFromMealPlan(Request $request)
    {
        $request->validate([
            'week_id' => 'required|exists:weeks,id',
            'title' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $list = $this->shoppingListService->generateFromMealPlan(
            $user->household_id,
            $request->week_id,
            $request->title
        );
        
        if (!$list) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($list);
    }
}

