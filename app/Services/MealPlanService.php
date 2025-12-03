<?php

namespace App\Services;

use App\Models\Week;
use App\Models\Meal;
use Carbon\Carbon;

class MealPlanService
{
    function getWeeklyPlan($householdId, $weekStartDate = null)
    {
        if (!$weekStartDate) {
            $weekStartDate = Carbon::now()->startOfWeek()->toDateString();
        }

        return Week::with(['meals.recipe'])
            ->where('household_id', $householdId)
            ->where('start_date', $weekStartDate)
            ->first();
    }

    function createWeeklyPlan($householdId, $startDate)
    {
        $startDate = Carbon::parse($startDate)->startOfWeek();
        $endDate = $startDate->copy()->endOfWeek();

        $week = Week::where('household_id', $householdId)
            ->where('start_date', $startDate->toDateString())
            ->first();

        if (!$week) {
            $week = new Week;
            $week->start_date = $startDate->toDateString();
            $week->end_date = $endDate->toDateString();
            $week->household_id = $householdId;
            $week->save();
        }

        $week->load(['meals.recipe']);
        return $week;
    }

    function addMeal($weekId, $householdId, $day, $slot, $recipeId)
    {
        $week = Week::where('id', $weekId)
            ->where('household_id', $householdId)
            ->first();

        if (!$week) {
            return null;
        }

        $existingMeal = Meal::where('week_id', $weekId)
            ->where('day', $day)
            ->where('slot', $slot)
            ->first();

        if ($existingMeal) {
            $existingMeal->recipe_id = $recipeId;
            $existingMeal->save();
            $existingMeal->load('recipe');
            
            return $existingMeal;
        }

        $meal = new Meal;
        $meal->week_id = $weekId;
        $meal->day = $day;
        $meal->slot = $slot;
        $meal->recipe_id = $recipeId;
        $meal->save();

        $meal->load('recipe');
        
        return $meal;
    }

    function removeMeal($weekId, $householdId, $mealId)
    {
        $week = Week::where('id', $weekId)
            ->where('household_id', $householdId)
            ->first();

        if (!$week) {
            return false;
        }

        $meal = Meal::where('id', $mealId)
            ->where('week_id', $weekId)
            ->first();

        if (!$meal) {
            return false;
        }

        $deleted = $meal->delete();
        
        return $deleted;
    }
}

