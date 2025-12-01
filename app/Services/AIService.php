<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Recipe;
use Illuminate\Support\Facades\Http;

class AIService
{
    private $apiKey;
    private $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY');
    }

    function getRecipeSuggestionsFromPantry($householdId, $limit = 5)
    {
        $pantry = Inventory::with('ingredient')
            ->where('household_id', $householdId)
            ->get();

        if ($pantry->isEmpty()) {
            return [];
        }

        $ingredients = $pantry->pluck('ingredient.name')->unique()->toArray();
        $ingredientsList = implode(', ', $ingredients);

        if (!$this->apiKey) {
            return $this->getBasicSuggestions($householdId, $limit);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful cooking assistant. Suggest recipes based on available ingredients.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "I have these ingredients in my pantry: {$ingredientsList}. Suggest {$limit} delicious and practical recipes I can make with these ingredients. For each recipe, provide:\n1. Recipe name\n2. Brief description (one sentence)\n\nFormat: Recipe Name - Description\n\nReturn only the recipes, one per line.",
                    ],
                ],
                'max_tokens' => 200,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $suggestions = $response->json()['choices'][0]['message']['content'];
                $recipeNames = array_filter(explode("\n", $suggestions));
                return array_slice($recipeNames, 0, $limit);
            }
        } catch (\Exception $e) {
            \Log::error('AI Service Error: ' . $e->getMessage());
        }

        return $this->getBasicSuggestions($householdId, $limit);
    }

    function getSmartSubstitutions($ingredientId, $householdId)
    {
        $ingredient = \App\Models\Ingredient::where('household_id', $householdId)->find($ingredientId);
        if (!$ingredient) {
            return [];
        }

        $pantry = Inventory::with('ingredient')
            ->where('household_id', $householdId)
            ->where('ingredient_id', '!=', $ingredientId)
            ->get()
            ->pluck('ingredient.name')
            ->toArray();

        if (!$this->apiKey || empty($pantry)) {
            return [];
        }

        try {
            $availableIngredients = implode(', ', $pantry);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful cooking assistant. Suggest ingredient substitutions.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "I need a substitute for {$ingredient->name} in a recipe. I have these ingredients available in my pantry: {$availableIngredients}. Suggest:\n1. The best substitute from my available ingredients (if any)\n2. If none match, suggest a common grocery store substitute\n3. Explain why it works as a substitute (one sentence)\n\nFormat: Substitute Name - Brief explanation",
                    ],
                ],
                'max_tokens' => 100,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $suggestion = $response->json()['choices'][0]['message']['content'];
                return ['substitution' => trim($suggestion)];
            }
        } catch (\Exception $e) {
            \Log::error('AI Service Error: ' . $e->getMessage());
        }

        return [];
    }

    private function getBasicSuggestions($householdId, $limit)
    {
        $pantryIngredientIds = Inventory::where('household_id', $householdId)
            ->pluck('ingredient_id')
            ->unique()
            ->toArray();

        $recipes = Recipe::with('ingredients')
            ->where('household_id', $householdId)
            ->whereHas('ingredients', function ($query) use ($pantryIngredientIds) {
                $query->whereIn('ingredients.id', $pantryIngredientIds);
            })
            ->limit($limit)
            ->get();

        return $recipes->pluck('title')->toArray();
    }

    /**
     * Generate seed data using AI (ingredients with nutrition, recipes, pantry items)
     */
    function generateSeedData($householdId)
    {
        if (!$this->apiKey) {
            return $this->getFallbackSeedData($householdId);
        }

        try {
            // Generate ingredients with nutrition info
            $ingredientsPrompt = "Generate a realistic list of 20 common kitchen ingredients with their nutritional information per 100g. For each ingredient, provide:
1. Name
2. Calories (per 100g)
3. Protein (grams per 100g)
4. Carbs (grams per 100g)
5. Fat (grams per 100g)
6. Default unit (g, kg, L, mL, piece, etc.)

Format as JSON array:
[{\"name\": \"Ingredient Name\", \"calories\": 100, \"protein\": 10, \"carbs\": 20, \"fat\": 5, \"unit\": \"g\"}, ...]

Return ONLY valid JSON, no other text.";

            $ingredientsResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a nutrition database assistant. Return only valid JSON.'],
                    ['role' => 'user', 'content' => $ingredientsPrompt],
                ],
                'max_tokens' => 2000,
                'temperature' => 0.3,
            ]);

            $ingredients = [];
            if ($ingredientsResponse->successful()) {
                $content = $ingredientsResponse->json()['choices'][0]['message']['content'];
                // Clean JSON (remove markdown code blocks if present)
                $content = preg_replace('/```json\n?/', '', $content);
                $content = preg_replace('/```\n?/', '', $content);
                $ingredients = json_decode(trim($content), true) ?? [];
            }

            // Generate recipes with full details
            $recipesPrompt = "Generate 10 diverse, practical recipes with full details. For each recipe, provide:
1. Title
2. Instructions (step-by-step, 3-5 steps)
3. Ingredients list (name, amount, unit) - use ingredients from common pantry
4. Servings (2-6)
5. Prep time (minutes, 5-30)
6. Cook time (minutes, 10-60)
7. Tags (array of 2-4 tags like [\"dinner\", \"easy\", \"italian\"])

Format as JSON array:
[{\"title\": \"Recipe Name\", \"instructions\": \"Step 1... Step 2...\", \"ingredients\": [{\"name\": \"Ingredient\", \"amount\": 100, \"unit\": \"g\"}], \"servings\": 4, \"prep_time\": 15, \"cook_time\": 30, \"tags\": [\"dinner\", \"easy\"]}, ...]

Return ONLY valid JSON, no other text.";

            $recipesResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a recipe database assistant. Return only valid JSON.'],
                    ['role' => 'user', 'content' => $recipesPrompt],
                ],
                'max_tokens' => 3000,
                'temperature' => 0.5,
            ]);

            $recipes = [];
            if ($recipesResponse->successful()) {
                $content = $recipesResponse->json()['choices'][0]['message']['content'];
                $content = preg_replace('/```json\n?/', '', $content);
                $content = preg_replace('/```\n?/', '', $content);
                $recipes = json_decode(trim($content), true) ?? [];
            }

            return [
                'ingredients' => $ingredients,
                'recipes' => $recipes,
            ];
        } catch (\Exception $e) {
            \Log::error('AI Seed Data Generation Error: ' . $e->getMessage());
            return $this->getFallbackSeedData($householdId);
        }
    }

    /**
     * Fallback seed data if AI is not available
     */
    private function getFallbackSeedData($householdId)
    {
        return [
            'ingredients' => [
                ['name' => 'Chicken Breast', 'calories' => 165, 'protein' => 31, 'carbs' => 0, 'fat' => 3.6, 'unit' => 'g'],
                ['name' => 'Rice', 'calories' => 130, 'protein' => 2.7, 'carbs' => 28, 'fat' => 0.3, 'unit' => 'g'],
                ['name' => 'Tomato', 'calories' => 18, 'protein' => 0.9, 'carbs' => 3.9, 'fat' => 0.2, 'unit' => 'g'],
                ['name' => 'Onion', 'calories' => 40, 'protein' => 1.1, 'carbs' => 9.3, 'fat' => 0.1, 'unit' => 'g'],
                ['name' => 'Olive Oil', 'calories' => 884, 'protein' => 0, 'carbs' => 0, 'fat' => 100, 'unit' => 'mL'],
            ],
            'recipes' => [
                [
                    'title' => 'Simple Chicken Rice',
                    'instructions' => 'Cook rice according to package. Season chicken with salt and pepper. Pan-fry chicken until cooked through. Serve chicken over rice.',
                    'ingredients' => [
                        ['name' => 'Chicken Breast', 'amount' => 200, 'unit' => 'g'],
                        ['name' => 'Rice', 'amount' => 150, 'unit' => 'g'],
                    ],
                    'servings' => 2,
                    'prep_time' => 10,
                    'cook_time' => 25,
                    'tags' => ['dinner', 'easy'],
                ],
            ],
        ];
    }
}

