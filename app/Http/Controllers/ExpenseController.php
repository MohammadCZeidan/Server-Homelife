<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ExpenseService;

class ExpenseController extends Controller
{
    private $expenseService;

    function __construct(ExpenseService $expenseService)
    {
        $this->expenseService = $expenseService;
    }

    function getAll(Request $request)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON([], "failure", status_code: 404);
        }

        $expenses = $this->expenseService->getAll($user->household_id);
        return $this->responseJSON($expenses);
    }

    function get($id)
    {
        $user = Auth::user();
        $expense = $this->expenseService->get($id, $user->household_id);
        
        if (!$expense) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($expense);
    }

    function create(Request $request)
    {
        $request->validate([
            'store' => 'nullable|string|max:255',                                                    
            'receipt_link' => 'nullable|url|max:255',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'category' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ]);

        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON(null, "failure", 404);
        }

        $expense = $this->expenseService->create($user->household_id, $request->all());
        return $this->responseJSON($expense);
    }

    function update(Request $request, $id)
    {
        $request->validate([
            'store' => 'nullable|string|max:255',
            'receipt_link' => 'nullable|url|max:255',
            'amount' => 'nullable|numeric|min:0',
            'date' => 'nullable|date',
            'category' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ]);

        $user = Auth::user();
        $expense = $this->expenseService->update($id, $user->household_id, $request->all());
        
        if (!$expense) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($expense);
    }

    function delete($id)
    {
        $user = Auth::user();
        $deleted = $this->expenseService->delete($id, $user->household_id);
        
        if (!$deleted) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON(null, "success");
    }

    function getSummary(Request $request)
    {
        $user = Auth::user();
        if (!$user->household_id) {
            return $this->responseJSON(null, "failure", 404);
        }

        $period = $request->get('period', 'week');
        $summary = $this->expenseService->getSummary($user->household_id, $period);
        return $this->responseJSON($summary);
    }
}

