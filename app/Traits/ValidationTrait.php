<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

trait ValidationTrait
{
    protected function getValidationRules($type, $context = [])
    {
        $controllerName = class_basename($this);
        $rules = [];

        switch ($controllerName) {
            case 'AuthController':
                $rules = $this->getAuthValidationRules($type, $context);
                break;

            case 'ExpenseController':
                $rules = $this->getExpenseValidationRules($type);
                break;

            case 'MealPlanController':
                $rules = $this->getMealPlanValidationRules($type, $context);
                break;

            case 'IngredientController':
                $rules = $this->getIngredientValidationRules($type);
                break;

            default:
                $rules = $this->getCustomValidationRules($type, $context);
                break;
        }

        return $rules;
    }

    protected function validateRequest(Request $request, $type, $context = [], $messages = [])
    {
        $rules = $this->getValidationRules($type, $context);
        
        if (empty($messages)) {
            return $request->validate($rules);
        }
        
        return $request->validate($rules, $messages);
    }

    private function getAuthValidationRules($type, $context = [])
    {
        $rules = [];

        switch ($type) {
            case 'login':
                $rules = [
                    'email' => 'required|string|email',
                    'password' => 'required|string',
                ];
                break;

            case 'register':
                $rules = [
                    'name' => 'required|string|max:255',
                    'email' => 'required|string|email|max:255|unique:users',
                    'password' => 'required|string|min:6',
                ];
                break;

            case 'updateProfile':
                $user = $context['user'] ?? Auth::user();
                $rules = [
                    'name' => 'nullable|string|max:255',
                    'email' => [
                        'nullable',
                        'string',
                        'email',
                        'max:255',
                        Rule::unique('users', 'email')->ignore($user->id)
                    ],
                ];
                break;
        }

        return $rules;
    }

    private function getExpenseValidationRules($type)
    {
        $rules = [];

        switch ($type) {
            case 'create':
                $rules = [
                    'store' => 'nullable|string|max:255',
                    'receipt_link' => 'nullable|url|max:255',
                    'amount' => 'required|numeric|min:0',
                    'date' => 'required|date',
                    'category' => 'nullable|string|max:255',
                    'note' => 'nullable|string',
                ];
                break;

            case 'update':
                $rules = [
                    'store' => 'nullable|string|max:255',
                    'receipt_link' => 'nullable|url|max:255',
                    'amount' => 'nullable|numeric|min:0',
                    'date' => 'nullable|date',
                    'category' => 'nullable|string|max:255',
                    'note' => 'nullable|string',
                ];
                break;
        }

        return $rules;
    }

    private function getMealPlanValidationRules($type, $context = [])
    {
        $rules = [];

        switch ($type) {
            case 'createWeeklyPlan':
                $rules = [
                    'start_date' => 'required|date',
                ];
                break;

            case 'addMeal':
                $user = $context['user'] ?? Auth::user();
                $rules = [
                    'day' => ['required', function ($attribute, $value, $fail) use ($user) {
                        $dayValue = $this->normalizeDay($value);
                        if ($dayValue === null || $dayValue < 0 || $dayValue > 6) {
                            $fail('Day must be 0-6 or a valid day name (e.g., "monday")');
                        }
                    }],
                    'recipe_id' => [
                        'required',
                        'integer',
                        Rule::exists('recipes', 'id')->where('household_id', $user->household_id)
                    ],
                    'slot' => ['nullable', Rule::in(['breakfast', 'lunch', 'dinner', 'snack'])],
                    'meal_type' => ['nullable', Rule::in(['breakfast', 'lunch', 'dinner', 'snack'])],
                ];
                break;
        }

        return $rules;
    }

    protected function normalizeDay($day)
    {
        if (is_numeric($day)) {
            $dayValue = (int)$day;
            return ($dayValue >= 0 && $dayValue <= 6) ? $dayValue : null;
        }

        if (is_string($day)) {
            $dayMap = [
                'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                'thursday' => 4, 'friday' => 5, 'saturday' => 6
            ];
            return $dayMap[strtolower($day)] ?? null;
        }

        return null;
    }

    private function getIngredientValidationRules($type)
    {
        $rules = [];

        switch ($type) {
            case 'create':
                $rules = [
                    'name' => 'required|string|max:255',
                    'calories' => 'nullable|numeric|min:0',
                    'protein' => 'nullable|numeric|min:0',
                    'carbs' => 'nullable|numeric|min:0',
                    'fat' => 'nullable|numeric|min:0',
                    'unit_id' => 'nullable|exists:units,id',
                ];
                break;
        }

        return $rules;
    }

    protected function getCustomValidationRules($type, $context = [])
    {
        return [];
    }
}

