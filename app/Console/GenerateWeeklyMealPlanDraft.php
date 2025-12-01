<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Household;
use App\Models\Inventory;
use App\Models\Week;
use App\Services\AIService;
use App\Services\MealPlanService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GenerateWeeklyMealPlanDraft extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pantry:generate-meal-plan-draft {--week-start= : Start date of week (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate weekly meal plan draft using AI based on pantry (for n8n WF2)';

    private $aiService;
    private $mealPlanService;

    public function __construct(AIService $aiService, MealPlanService $mealPlanService)
    {
        parent::__construct();
        $this->aiService = $aiService;
        $this->mealPlanService = $mealPlanService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $weekStart = $this->option('week-start') 
            ? Carbon::parse($this->option('week-start'))
            : Carbon::now()->next(Carbon::SUNDAY)->startOfDay();

        $this->info("Generating meal plan drafts for week starting: {$weekStart->format('Y-m-d')}");

        $households = Household::all();
        $totalDrafts = 0;

        foreach ($households as $household) {
            // Get pantry items
            $pantry = Inventory::with('ingredient')
                ->where('household_id', $household->id)
                ->where('quantity', '>', 0)
                ->get();

            if ($pantry->isEmpty()) {
                $this->warn("Household {$household->name} has no pantry items, skipping...");
                continue;
            }

            $ingredients = $pantry->pluck('ingredient.name')->unique()->toArray();
            $ingredientsList = implode(', ', $ingredients);

            // Use AI to generate meal suggestions
            $suggestions = $this->aiService->getRecipeSuggestionsFromPantry($household->id, 21); // 21 meals for a week

            if (empty($suggestions)) {
                $this->warn("No AI suggestions for household {$household->name}, skipping...");
                continue;
            }

            // Create draft week
            $week = Week::firstOrCreate(
                [
                    'household_id' => $household->id,
                    'start_date' => $weekStart->toDateString(),
                ],
                [
                    'end_date' => $weekStart->copy()->addDays(6)->toDateString(),
                ]
            );

            // Log draft for n8n to process
            $draftData = [
                'household_id' => $household->id,
                'household_name' => $household->name,
                'week_id' => $week->id,
                'week_start' => $weekStart->toDateString(),
                'available_ingredients' => $ingredients,
                'suggested_meals' => is_array($suggestions) ? $suggestions : [$suggestions],
                'generated_at' => Carbon::now()->toDateTimeString(),
            ];

            // Log for n8n
            Log::info('Meal Plan Draft Generated', $draftData);

            $this->line("Household: {$household->name} - {$household->id}");
            $this->line("  Week ID: {$week->id}");
            $this->line("  Available ingredients: " . count($ingredients));
            $this->line("  Suggested meals: " . (is_array($suggestions) ? count($suggestions) : 1));
            $this->newLine();

            $totalDrafts++;
        }

        $this->info("Total meal plan drafts generated: {$totalDrafts}");
        
        return Command::SUCCESS;
    }
}
