<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\Week;
use App\Models\Meal;

class NutritionService
{
    function getRecipeNutrition($recipeId, $householdId)
    {
        $recipe = Recipe::with('ingredients')
            ->where('id', $recipeId)
            ->where('household_id', $householdId)
            ->first();

        if (!$recipe) {
            return null;
        }

        $totalCalories = 0;
        $totalProtein = 0;
        $totalCarbs = 0;
        $totalFat = 0;

        foreach ($recipe->ingredients as $ingredient) {
            $pivot = $ingredient->pivot;
            $quantity = $pivot->quantity;
            
            $totalCalories += ($ingredient->calories * $quantity) / 100;
            $totalProtein += ($ingredient->protein * $quantity) / 100;
            $totalCarbs += ($ingredient->carbs * $quantity) / 100;
            $totalFat += ($ingredient->fat * $quantity) / 100;
        }

        $servings = $recipe->servings ?? 1;
        $perServing = [
            'calories' => round($totalCalories / $servings, 2),
            'protein' => round($totalProtein / $servings, 2),
            'carbs' => round($totalCarbs / $servings, 2),
            'fat' => round($totalFat / $servings, 2),
        ];

        return [
            'recipe_id' => $recipe->id,
            'recipe_title' => $recipe->title,
            'servings' => $servings,
            'total' => [
                'calories' => round($totalCalories, 2),
                'protein' => round($totalProtein, 2),
                'carbs' => round($totalCarbs, 2),
                'fat' => round($totalFat, 2),
            ],
            'per_serving' => $perServing,
        ];
    }

    function getWeeklyNutrition($weekId, $householdId)
    {
        $week = Week::where('id', $weekId)
            ->where('household_id', $householdId)
            ->first();

        if (!$week) {
            return null;
        }

        $meals = Meal::with('recipe.ingredients')->where('week_id', $weekId)->get();

        $totalCalories = 0;
        $totalProtein = 0;
        $totalCarbs = 0;
        $totalFat = 0;
        $mealsByDay = [];

        foreach ($meals as $meal) {
            if (!$meal->recipe) continue;

            $dayNutrition = [
                'calories' => 0,
                'protein' => 0,
                'carbs' => 0,
                'fat' => 0,
            ];

            foreach ($meal->recipe->ingredients as $ingredient) {
                $pivot = $ingredient->pivot;
                $quantity = $pivot->quantity;
                
                $calories = ($ingredient->calories * $quantity) / 100;
                $protein = ($ingredient->protein * $quantity) / 100;
                $carbs = ($ingredient->carbs * $quantity) / 100;
                $fat = ($ingredient->fat * $quantity) / 100;

                $servings = $meal->recipe->servings ?? 1;
                $dayNutrition['calories'] += $calories / $servings;
                $dayNutrition['protein'] += $protein / $servings;
                $dayNutrition['carbs'] += $carbs / $servings;
                $dayNutrition['fat'] += $fat / $servings;
            }

            $day = $meal->day;
            if (!isset($mealsByDay[$day])) {
                $mealsByDay[$day] = [
                    'calories' => 0,
                    'protein' => 0,
                    'carbs' => 0,
                    'fat' => 0,
                ];
            }

            $mealsByDay[$day]['calories'] += round($dayNutrition['calories'], 2);
            $mealsByDay[$day]['protein'] += round($dayNutrition['protein'], 2);
            $mealsByDay[$day]['carbs'] += round($dayNutrition['carbs'], 2);
            $mealsByDay[$day]['fat'] += round($dayNutrition['fat'], 2);

            $totalCalories += $dayNutrition['calories'];
            $totalProtein += $dayNutrition['protein'];
            $totalCarbs += $dayNutrition['carbs'];
            $totalFat += $dayNutrition['fat'];
        }

        return [
            'week_id' => $week->id,
            'start_date' => $week->start_date,
            'end_date' => $week->end_date,
            'total' => [
                'calories' => round($totalCalories, 2),
                'protein' => round($totalProtein, 2),
                'carbs' => round($totalCarbs, 2),
                'fat' => round($totalFat, 2),
            ],
            'daily_average' => [
                'calories' => round($totalCalories / 7, 2),
                'protein' => round($totalProtein / 7, 2),
                'carbs' => round($totalCarbs / 7, 2),
                'fat' => round($totalFat / 7, 2),
            ],
            'by_day' => $mealsByDay,
        ];
    }
}

