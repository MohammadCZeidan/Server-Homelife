<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\ExpenseService;

class ExpenseController extends Controller
{
    private ExpenseService $expenseService;

    public function __construct(ExpenseService $expenseService)
    {
        $this->expenseService = $expenseService;
    }

    public function getAll(Request $request): JsonResponse{
        $user = Auth::user();
        $expenses = $this->expenseService->getAll($user->household_id);
        return $this->responseJSON($expenses);
    }

    public function get($id): JsonResponse
    {
        $user = Auth::user();
        $expense = $this->expenseService->get($id, $user->household_id);
        
        if (!$expense) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($expense);
    }

    public function create(Request $request): JsonResponse
    {
        $this->validateRequest($request, 'create');

        $user = Auth::user();
        $expense = $this->expenseService->create($user->household_id, $request->all());
        return $this->responseJSON($expense);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $this->validateRequest($request, 'update');

        $user = Auth::user();
        $expense = $this->expenseService->update($id, $user->household_id, $request->all());
        
        if (!$expense) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($expense);
    }

    public function delete($id): JsonResponse{
        $user = Auth::user();
        $deleted = $this->expenseService->delete($id, $user->household_id);
        
        if (!$deleted) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON(null, "success");
    }

    public function getSummary(Request $request): JsonResponse
    {
        $user = Auth::user();
        $period = $request->get('period', 'week');
        $summary = $this->expenseService->getSummary($user->household_id, $period);
        return $this->responseJSON($summary);
    }
}

