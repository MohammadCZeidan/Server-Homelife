<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Inventory;
use App\Models\Household;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendExpiryAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pantry:send-expiry-alerts {--days=3 : Number of days ahead to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily expiry alerts for items expiring soon (for n8n WF1)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $expiryDate = Carbon::now()->addDays($days);
        
        $this->info("Checking for items expiring in the next {$days} days...");

        $households = Household::with('users')->get();
        $totalAlerts = 0;

        foreach ($households as $household) {
            $expiringItems = Inventory::with(['ingredient', 'unit'])
                ->where('household_id', $household->id)
                ->whereNotNull('expiry_date')
                ->where('expiry_date', '<=', $expiryDate)
                ->where('expiry_date', '>=', Carbon::now())
                ->orderBy('expiry_date', 'asc')
                ->get();

            if ($expiringItems->isEmpty()) {
                continue;
            }

            $items = $expiringItems->map(function ($item) {
                $daysUntil = Carbon::now()->diffInDays($item->expiry_date, false);
                return [
                    'ingredient' => $item->ingredient->name,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit->name ?? 'units',
                    'expiry_date' => $item->expiry_date->format('Y-m-d'),
                    'days_until_expiry' => $daysUntil,
                    'location' => $item->location,
                ];
            })->toArray();

            // Log for n8n to pick up (or send via webhook/email/telegram)
            $alertData = [
                'household_id' => $household->id,
                'household_name' => $household->name,
                'users' => $household->users->pluck('email')->toArray(),
                'expiring_items' => $items,
                'count' => count($items),
                'alert_date' => Carbon::now()->toDateString(),
            ];

            // Log to file for n8n to process
            Log::info('Expiry Alert', $alertData);
            
            // Also output to console
            $this->line("Household: {$household->name} - {$household->id}");
            $this->line("  Users: " . implode(', ', $household->users->pluck('email')->toArray()));
            $this->line("  Expiring items: " . count($items));
            foreach ($items as $item) {
                $this->line("    - {$item['ingredient']} ({$item['quantity']} {$item['unit']}) expires in {$item['days_until_expiry']} days");
            }
            $this->newLine();

            $totalAlerts += count($items);
        }

        $this->info("Total alerts sent: {$totalAlerts}");
        
        return Command::SUCCESS;
    }
}
