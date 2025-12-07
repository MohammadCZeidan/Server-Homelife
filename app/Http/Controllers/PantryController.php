<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\PantryService;
use App\Models\Inventory;

class PantryController extends Controller
{
    private PantryService $pantryService;

    public function __construct(PantryService $pantryService)
    {
        $this->pantryService = $pantryService;
    }

    public function getAll(Request $request): JsonResponse
    {
        $user = Auth::user();
        $inventory = $this->pantryService->getAll($user->household_id);
        return $this->responseJSON($inventory);
    }

    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0',
            'unit_id' => 'required|exists:units,id',
            'expiry_date' => 'nullable|date',
            'location' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $inventory = $this->pantryService->create($user->household_id, $request->all());
        return $this->responseJSON($inventory);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        // Get the inventory item to access its ingredient_id
        $inventory = Inventory::where('id', $id)
            ->where('household_id', $user->household_id)
            ->first();

        if (!$inventory) {
            return $this->responseJSON(null, "failure", 404);
        }

        // Validate with unique ingredient name check (ignore current ingredient)
        $ingredientName = $request->input('ingredient_name') ?? $request->input('name');
        $validationRules = [
            'quantity' => 'nullable|numeric|min:0',
            'unit_id' => 'nullable|exists:units,id',
            'expiry_date' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'ingredient_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'calories' => 'nullable|numeric|min:0',
            'protein' => 'nullable|numeric|min:0',
            'carbs' => 'nullable|numeric|min:0',
            'fat' => 'nullable|numeric|min:0',
        ];

        // If ingredient name is being updated, validate uniqueness
        if ($ingredientName) {
            $validationRules['ingredient_name'] = [
                'nullable',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('ingredients', 'name')
                    ->where('household_id', $user->household_id)
                    ->ignore($inventory->ingredient_id)
            ];
            $validationRules['name'] = [
                'nullable',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('ingredients', 'name')
                    ->where('household_id', $user->household_id)
                    ->ignore($inventory->ingredient_id)
            ];
        }

        $request->validate($validationRules);

        $inventory = $this->pantryService->update($id, $user->household_id, $request->all());
        
        if (!$inventory) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($inventory);
    }

    public function delete(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        // Validate that ID is numeric
        if (!is_numeric($id)) {
            return $this->responseJSON(null, "failure", 400);
        }

        $deleted = $this->pantryService->delete($id, $user->household_id);
        
        if (!$deleted) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON(null, "success");
    }

    public function consume(Request $request, $id): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|numeric|min:0',
        ]);

        $user = Auth::user();
        $result = $this->pantryService->consume($id, $user->household_id, $request->quantity);
        
        if (!$result) {
            return $this->responseJSON(null, "failure", 404);
        }

        if ($result['deleted']) {
            return $this->responseJSON(null, "success");
        }

        return $this->responseJSON($result['inventory']);
    }

    public function getExpiringSoon(Request $request): JsonResponse
    {
        $user = Auth::user();
        $days = (int) $request->get('days', 7);
        $inventory = $this->pantryService->getExpiringSoon($user->household_id, $days);
        
        // Add "use first" badge logic (items expiring in 1-2 days get priority)
        $items = $inventory->map(function ($item) {
            if (!$item->expiry_date) {
                return $item;
            }
            
            $expiryDate = \Carbon\Carbon::parse($item->expiry_date);
            $now = \Carbon\Carbon::now();
            $daysUntil = $now->diffInDays($expiryDate, false);
            
            $item->use_first = $daysUntil <= 2 && $daysUntil >= 0;
            $item->days_until_expiry = $daysUntil;
            $item->expiry_date = $expiryDate->format('Y-m-d');
            
            return $item;
        });
        
        return $this->responseJSON($items);
    }
    
    public function updateExpiryDate(Request $request, $id): JsonResponse
    {
        $request->validate([
            'expiry_date' => 'required|date',
        ]);

        $user = Auth::user();
        $inventory = $this->pantryService->update($id, $user->household_id, [
            'expiry_date' => $request->expiry_date
        ]);
        
        if (!$inventory) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($inventory);
    }

    public function mergeDuplicates(): JsonResponse
    {
        $user = Auth::user();
        $result = $this->pantryService->mergeDuplicates($user->household_id);
        return $this->responseJSON($result, "success");
    }

    /**
     * Send expiring ingredients email via n8n
     * Called from frontend React - sends email to mohammadz20012001@gmail.com and logged-in user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function sendExpiringItemsEmail(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                \Log::error('sendExpiringItemsEmail: User not authenticated');
                return $this->responseJSON(null, "failure", 401, "User not authenticated");
            }
            
            // Validate user has household
            if (!$user->household_id) {
                \Log::warning('sendExpiringItemsEmail: User has no household', ['user_id' => $user->id]);
                return $this->responseJSON(null, "failure", 400, "User must belong to a household");
            }

            // Get expiring items (default 7 days)
            $days = (int) $request->get('days', 7);
            
            try {
                $expiringItems = $this->pantryService->getExpiringSoon($user->household_id, $days);
            } catch (\Exception $e) {
                \Log::error('sendExpiringItemsEmail: Error fetching expiring items', [
                    'user_id' => $user->id,
                    'household_id' => $user->household_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return $this->responseJSON(null, "failure", 500, "Error fetching expiring items: " . $e->getMessage());
            }
            
            // Format items with expiry info
            $formattedItems = $expiringItems->map(function ($item) {
                try {
                    if (!$item->expiry_date) {
                        return null;
                    }
                    
                    $expiryDate = \Carbon\Carbon::parse($item->expiry_date);
                    $now = \Carbon\Carbon::now();
                    $daysUntil = $now->diffInDays($expiryDate, false);
                    
                    return [
                        'id' => $item->id,
                        'ingredient_name' => $item->ingredient->name ?? 'Unknown',
                        'quantity' => $item->quantity,
                        'unit' => $item->unit->name ?? 'unit',
                        'expiry_date' => $expiryDate->format('Y-m-d'),
                        'days_until_expiry' => $daysUntil,
                        'location' => $item->location,
                    ];
                } catch (\Exception $e) {
                    \Log::warning('sendExpiringItemsEmail: Error formatting item', [
                        'item_id' => $item->id ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    return null;
                }
            })->filter(); // Remove null items

            // Prepare email data
            try {
                $emailData = [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'household_id' => $user->household_id,
                    'recipient_emails' => [
                        'mohammadz20012001@gmail.com', // Always include this
                        $user->email, // Logged-in user's email
                    ],
                    'expiring_items' => $formattedItems->toArray(),
                    'days' => $days,
                    'subject' => 'Items Expiring Soon - HomeLife',
                    'message' => $this->formatExpiringItemsEmail($formattedItems, $user->name),
                ];
            } catch (\Exception $e) {
                \Log::error('sendExpiringItemsEmail: Error preparing email data', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return $this->responseJSON(null, "failure", 500, "Error preparing email data: " . $e->getMessage());
            }

            // Send to n8n webhook using WebhookService
            try {
                $webhookService = app(\App\Services\WebhookService::class);
                $result = $webhookService->sendExpiringItemsEmail($emailData);

                if ($result['success']) {
                    \Log::info('sendExpiringItemsEmail: Email sent successfully', [
                        'user_id' => $user->id,
                        'items_count' => $formattedItems->count(),
                        'recipients' => $emailData['recipient_emails']
                    ]);
                    
                    return $this->responseJSON([
                        'message' => $result['message'],
                        'items_count' => $formattedItems->count(),
                        'recipients' => $emailData['recipient_emails'],
                    ], "success");
                } else {
                    \Log::error('sendExpiringItemsEmail: n8n webhook failed', [
                        'message' => $result['message'],
                        'user_id' => $user->id
                    ]);
                    
                    return $this->responseJSON(null, "failure", 500, $result['message']);
                }
            } catch (\Exception $e) {
                \Log::error('sendExpiringItemsEmail: Error sending to n8n', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'user_id' => $user->id
                ]);
                
                return $this->responseJSON(null, "failure", 500, "Error sending email: " . $e->getMessage());
            }
        } catch (\Exception $e) {
            \Log::error('sendExpiringItemsEmail: Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->responseJSON(null, "failure", 500, "Unexpected error: " . $e->getMessage());
        }
    }

    /**
     * Format expiring items into HTML email message
     * 
     * @param \Illuminate\Support\Collection $items
     * @param string $userName
     * @return string
     */
    private function formatExpiringItemsEmail($items, $userName)
    {
        if ($items->isEmpty()) {
            return "<p>Hello {$userName},</p><p>You have no items expiring soon.</p>";
        }

        // Group items by urgency
        $expired = $items->filter(fn($item) => $item['days_until_expiry'] < 0);
        $tomorrow = $items->filter(fn($item) => $item['days_until_expiry'] === 0);
        $thisWeek = $items->filter(fn($item) => $item['days_until_expiry'] > 0 && $item['days_until_expiry'] <= 3);
        $nextWeek = $items->filter(fn($item) => $item['days_until_expiry'] > 3 && $item['days_until_expiry'] <= 7);

        $html = "<!DOCTYPE html><html><head><style>";
        $html .= "body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }";
        $html .= ".container { max-width: 600px; margin: 0 auto; padding: 20px; }";
        $html .= ".header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }";
        $html .= ".content { padding: 20px; background-color: #f9f9f9; }";
        $html .= ".section { margin-bottom: 20px; }";
        $html .= ".section h3 { color: #4CAF50; margin-top: 0; }";
        $html .= "ul { list-style-type: none; padding: 0; }";
        $html .= "li { padding: 8px; margin: 5px 0; background-color: white; border-left: 4px solid #4CAF50; }";
        $html .= ".expired { border-left-color: #f44336; }";
        $html .= ".urgent { border-left-color: #ff9800; }";
        $html .= ".footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }";
        $html .= "</style></head><body>";
        $html .= "<div class='container'>";
        $html .= "<div class='header'><h1>HomeLife - Expiring Items Alert</h1></div>";
        $html .= "<div class='content'>";
        $html .= "<p>Hello {$userName},</p>";
        $html .= "<p>Here are the items in your pantry that are expiring soon:</p>";

        if ($expired->count() > 0) {
            $html .= "<div class='section'><h3>⚠️ Expired ({$expired->count()})</h3><ul>";
            foreach ($expired as $item) {
                $html .= "<li class='expired'><strong>{$item['ingredient_name']}</strong> - {$item['quantity']} {$item['unit']} - Expired on {$item['expiry_date']}</li>";
            }
            $html .= "</ul></div>";
        }

        if ($tomorrow->count() > 0) {
            $html .= "<div class='section'><h3>🔴 Expiring Tomorrow ({$tomorrow->count()})</h3><ul>";
            foreach ($tomorrow as $item) {
                $html .= "<li class='urgent'><strong>{$item['ingredient_name']}</strong> - {$item['quantity']} {$item['unit']} - Expires tomorrow!</li>";
            }
            $html .= "</ul></div>";
        }

        if ($thisWeek->count() > 0) {
            $html .= "<div class='section'><h3>🟡 This Week ({$thisWeek->count()})</h3><ul>";
            foreach ($thisWeek as $item) {
                $html .= "<li><strong>{$item['ingredient_name']}</strong> - {$item['quantity']} {$item['unit']} - {$item['days_until_expiry']} days left (expires {$item['expiry_date']})</li>";
            }
            $html .= "</ul></div>";
        }

        if ($nextWeek->count() > 0) {
            $html .= "<div class='section'><h3>🟢 Next Week ({$nextWeek->count()})</h3><ul>";
            foreach ($nextWeek as $item) {
                $html .= "<li><strong>{$item['ingredient_name']}</strong> - {$item['quantity']} {$item['unit']} - {$item['days_until_expiry']} days left (expires {$item['expiry_date']})</li>";
            }
            $html .= "</ul></div>";
        }

        $html .= "</div>";
        $html .= "<div class='footer'><p>© 2025 HomeLife. All rights reserved.</p></div>";
        $html .= "</div></body></html>";

        return $html;
    }
}

