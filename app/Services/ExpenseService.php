<?php

namespace App\Services;

use App\Models\Expense;
use Carbon\Carbon;

class ExpenseService
{
    function getAll($householdId)
    {
        return Expense::where('household_id', $householdId)
            ->orderBy('date', 'desc')
            ->get();
    }

    function get($id, $householdId)
    {
        return Expense::where('id', $id)
            ->where('household_id', $householdId)
            ->first();
    }

    function create($householdId, $data)
    {
        $expense = new Expense;
        $expense->household_id = $householdId;
        $expense->store = $data['store'] ?? null;
        $expense->receipt_link = $data['receipt_link'] ?? null;
        $expense->amount = $data['amount'];
        $expense->date = $data['date'];
        $expense->category = $data['category'] ?? null;
        $expense->note = $data['note'] ?? null;
        $expense->save();

        return $expense;
    }

    function update($id, $householdId, $data)
    {
        $expense = Expense::where('id', $id)
            ->where('household_id', $householdId)
            ->first();

        if (!$expense) {
            return null;
        }

        if (isset($data['store'])) {
            $expense->store = $data['store'];
        }
        if (isset($data['receipt_link'])) {
            $expense->receipt_link = $data['receipt_link'];
        }
        if (isset($data['amount'])) {
            $expense->amount = $data['amount'];
        }
        if (isset($data['date'])) {
            $expense->date = $data['date'];
        }
        if (isset($data['category'])) {
            $expense->category = $data['category'];
        }
        if (isset($data['note'])) {
            $expense->note = $data['note'];
        }
        $expense->save();

        return $expense;
    }

    function delete($id, $householdId)
    {
        $expense = Expense::where('id', $id)
            ->where('household_id', $householdId)
            ->first();

        if (!$expense) {
            return false;
        }

        return $expense->delete();
    }

    function getSummary($householdId, $period = 'week')
    {
        $now = Carbon::now();

        if ($period === 'week') {
            $startDate = $now->copy()->startOfWeek();
            $endDate = $now->copy()->endOfWeek();
        } else {
            $startDate = $now->copy()->startOfMonth();
            $endDate = $now->copy()->endOfMonth();
        }

        $expenses = Expense::where('household_id', $householdId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $total = $expenses->sum('amount');
        $byCategory = $expenses->groupBy('category')->map(function ($items) {
            return $items->sum('amount');
        });

        return [
            'period' => $period,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total' => $total,
            'count' => $expenses->count(),
            'by_category' => $byCategory,
        ];
    }
}

