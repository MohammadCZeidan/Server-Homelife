# 🏠 HomeLife Backend Codebase - Complete Explanation

## 📋 Table of Contents
1. [How the Backend is Called](#how-the-backend-is-called)
2. [Request Flow Architecture](#request-flow-architecture)
3. [File-by-File Breakdown](#file-by-file-breakdown)
4. [Complex Code Sections Explained](#complex-code-sections-explained)

---

## 🔌 How the Backend is Called

### Entry Point: `routes/api.php`

**This is where ALL API requests start!** When a frontend (React, Vue, mobile app) makes an HTTP request, it hits one of these routes.

#### Example API Call Flow:
```
Frontend Request: POST https://yourserver.com/api/v0.1/auth/login
                    ↓
routes/api.php (line 34) → Route::post('/login', [AuthController::class, 'login'])
                    ↓
AuthController::login() method
                    ↓
AuthService::login() method
                    ↓
Returns JSON response with user + JWT token
```

### Route Structure Breakdown

```php
// routes/api.php

// 1. API VERSION PREFIX
Route::prefix('v0.1')->group(function () {
    // All routes are under /api/v0.1/
    
    // 2. PUBLIC ROUTES (No authentication needed)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });
    
    // 3. PROTECTED ROUTES (Require JWT token)
    Route::prefix('auth')->middleware('auth:api')->group(function () {
        // 'auth:api' middleware checks for valid JWT token
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
    
    // 4. HOUSEHOLD ROUTES (Protected)
    Route::prefix('household')->middleware('auth:api')->group(function () {
        Route::get('/', [HouseholdController::class, 'get']);
        Route::post('/', [HouseholdController::class, 'create']);
    });
    
    // ... and so on for other features
});
```

### How Frontend Calls These Routes

**Example: User Login**
```javascript
// Frontend JavaScript/React
fetch('https://yourserver.com/api/v0.1/auth/login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        email: 'user@example.com',
        password: 'password123'
    })
})
.then(response => response.json())
.then(data => {
    // data = { status: "success", payload: { user: {...}, token: "jwt_token_here" } }
    localStorage.setItem('token', data.payload.token);
});
```

**Example: Get Pantry Items (Protected Route)**
```javascript
fetch('https://yourserver.com/api/v0.1/pantry', {
    method: 'GET',
    headers: {
        'Authorization': 'Bearer ' + localStorage.getItem('token'),
        'Content-Type': 'application/json',
    }
})
.then(response => response.json())
.then(data => {
    // data = { status: "success", payload: [inventory items...] }
});
```

---

## 🔄 Request Flow Architecture

### Complete Request Journey

```
1. HTTP Request arrives
   ↓
2. Laravel Router (routes/api.php) matches URL pattern
   ↓
3. Middleware runs (e.g., 'auth:api' checks JWT token)
   ↓
4. Controller method executes (e.g., PantryController::getAll)
   ↓
5. Controller validates request data
   ↓
6. Controller calls Service method (e.g., PantryService::getAll)
   ↓
7. Service queries database using Eloquent Models
   ↓
8. Service returns data to Controller
   ↓
9. Controller formats response using ResponseTrait
   ↓
10. JSON response sent back to frontend
```

### Layer Responsibilities

| Layer | Responsibility | Example |
|-------|---------------|---------|
| **Routes** | URL mapping, middleware assignment | `routes/api.php` |
| **Controllers** | Request validation, HTTP response formatting | `PantryController` |
| **Services** | Business logic, database operations | `PantryService` |
| **Models** | Database tables, relationships | `Inventory`, `Ingredient` |
| **Traits** | Reusable code snippets | `ResponseTrait` |

---

## 📁 File-by-File Breakdown

### 1. `routes/api.php` - The API Gateway

**Purpose:** Defines all API endpoints and maps them to controller methods.

**Key Sections:**

```php
// Line 30-35: Public authentication (no login required)
Route::prefix('v0.1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });
```

**Explanation:**
- `Route::prefix('v0.1')` means all routes start with `/api/v0.1/`
- `Route::post()` handles POST requests
- `[AuthController::class, 'login']` means: "Call the `login` method in `AuthController`"

```php
// Line 38-43: Protected routes (require authentication)
Route::prefix('auth')->middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});
```

**Explanation:**
- `middleware('auth:api')` runs BEFORE the controller method
- It checks if the request has a valid JWT token in the `Authorization` header
- If no token or invalid token → returns 401 Unauthorized

---

### 2. `app/Http/Controllers/AuthController.php` - Authentication Handler

**Purpose:** Handles user registration, login, logout, and profile management.

#### Simple Method: `login()`

```php
// Lines 22-36
public function login(Request $request)
{
    // 1. VALIDATE INPUT
    $request->validate([
        'email' => 'required|string|email',
        'password' => 'required|string',
    ]);

    // 2. CALL SERVICE TO DO THE ACTUAL WORK
    $user = $this->authService->login($request->email, $request->password);
    
    // 3. CHECK IF LOGIN FAILED
    if (!$user) {
        return $this->responseJSON(null, "failure", 401);
    }

    // 4. RETURN SUCCESS RESPONSE
    return $this->responseJSON($user);
}
```

**Step-by-Step:**
1. **Validation:** Ensures email and password are provided and email is valid format
2. **Service Call:** Delegates actual login logic to `AuthService`
3. **Error Handling:** If login fails (wrong password), return 401 error
4. **Success Response:** Returns user data with JWT token

#### Complex Method: `updateProfile()`

```php
// Lines 68-85
public function updateProfile(Request $request)
{
    // Get current authenticated user
    $user = $this->authService->me();
    
    // Complex validation: email must be unique EXCEPT for current user
    $request->validate([
        'name' => 'nullable|string|max:255',
        'email' => [
            'nullable',
            'string',
            'email',
            'max:255',
            \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)
        ],
    ]);

    $user = $this->authService->updateProfile($request->all());
    return $this->responseJSON($user);
}
```

**Why This is Complex:**
- `Rule::unique('users', 'email')->ignore($user->id)` means:
  - "Email must be unique in the `users` table"
  - "BUT ignore the current user's email (so they can keep their own email)"
  - This prevents: "Email already taken" error when user doesn't change their email

---

### 3. `app/Services/AuthService.php` - Authentication Business Logic

**Purpose:** Contains the actual authentication logic (separated from HTTP concerns).

#### Method: `login()`

```php
// Lines 11-23
function login($email, $password)
{
    // 1. Prepare credentials array
    $credentials = ['email' => $email, 'password' => $password];
    
    // 2. Attempt authentication using JWT
    $token = Auth::guard('api')->attempt($credentials);

    // 3. If login fails, return null
    if (!$token) {
        return null;
    }

    // 4. Get authenticated user
    $user = Auth::guard('api')->user();
    
    // 5. Attach token to user object
    $user->token = $token;
    
    return $user;
}
```

**Key Concepts:**
- `Auth::guard('api')` uses JWT (JSON Web Token) authentication instead of sessions
- `attempt()` checks email/password against database
- If valid, returns a JWT token string
- Token is attached to user object so frontend can store it

#### Method: `register()`

```php
// Lines 25-36
function register($name, $email, $password)
{
    // 1. Create new User model instance
    $user = new User;
    $user->name = $name;
    $user->email = $email;
    
    // 2. Hash password (NEVER store plain text passwords!)
    $user->password = Hash::make($password);
    
    // 3. Save to database
    $user->save();

    // 4. Automatically log in the new user
    $token = Auth::guard('api')->login($user);
    $user->token = $token;
    
    return $user;
}
```

**Security Note:**
- `Hash::make($password)` uses bcrypt to hash passwords
- Original password is never stored, only the hash
- When user logs in, Laravel compares hash of entered password with stored hash

---

### 4. `app/Http/Controllers/PantryController.php` - Pantry Management

**Purpose:** Handles all pantry/inventory operations.

#### Method: `getAll()` - Simple Example

```php
// Lines 18-31
function getAll(Request $request)
{
    // 1. Get authenticated user
    $user = Auth::user();
    
    // 2. Check if user belongs to a household
    if (!$user->household_id) {
        return response()->json([
            "status" => "failure",
            "payload" => null,
            "message" => "You must create or join a household first..."
        ], 400);
    }

    // 3. Get all inventory items for user's household
    $inventory = $this->pantryService->getAll($user->household_id);
    
    // 4. Return formatted JSON response
    return $this->responseJSON($inventory);
}
```

**Why Check `household_id`?**
- This app is multi-tenant (multiple households)
- Each user belongs to one household
- All pantry items are scoped to a household
- If user has no household, they can't have pantry items

#### Complex Method: `update()` - Advanced Validation

```php
// Lines 56-120
function update(Request $request, $id)
{
    $user = Auth::user();
    
    // 1. Verify inventory item exists and belongs to user's household
    $inventory = \App\Models\Inventory::where('id', $id)
        ->where('household_id', $user->household_id)
        ->first();

    if (!$inventory) {
        return $this->responseJSON(null, "failure", 404);
    }

    // 2. Complex validation with conditional rules
    $ingredientName = $request->input('ingredient_name') ?? $request->input('name');
    $validationRules = [
        'quantity' => 'nullable|numeric|min:0',
        'unit_id' => 'nullable|exists:units,id',
        // ... more rules
    ];

    // 3. DYNAMIC VALIDATION: Only validate uniqueness if name is being changed
    if ($ingredientName) {
        $validationRules['ingredient_name'] = [
            'nullable',
            'string',
            'max:255',
            \Illuminate\Validation\Rule::unique('ingredients', 'name')
                ->where('household_id', $user->household_id)
                ->ignore($inventory->ingredient_id)
        ];
    }

    $request->validate($validationRules);
    
    // 4. Call service to perform update
    $inventory = $this->pantryService->update($id, $user->household_id, $request->all());
    
    return $this->responseJSON($inventory);
}
```

**Why This is Complex:**
1. **Security Check:** Verifies item belongs to user's household (prevents accessing other households' data)
2. **Dynamic Validation:** Validation rules change based on what's being updated
3. **Scoped Uniqueness:** Ingredient name must be unique WITHIN the household (not globally)

---

### 5. `app/Services/PantryService.php` - Pantry Business Logic

**Purpose:** Contains all pantry-related database operations.

#### Simple Method: `getAll()`

```php
// Lines 11-16
function getAll($householdId)
{
    return Inventory::with(['ingredient', 'unit'])
        ->where('household_id', $householdId)
        ->get();
}
```

**Explanation:**
- `Inventory::with(['ingredient', 'unit'])` uses **Eager Loading**
- Instead of making 3 separate database queries, it loads related data in one query
- Returns all inventory items with their ingredient and unit information attached

#### Complex Method: `create()` - Duplicate Detection & Merging

```php
// Lines 18-49
function create($householdId, $data)
{
    // 1. CHECK FOR DUPLICATES
    // Look for existing item with same ingredient, unit, expiry, location
    $existing = Inventory::where('household_id', $householdId)
        ->where('ingredient_id', $data['ingredient_id'])
        ->where('unit_id', $data['unit_id'])
        ->where('expiry_date', $data['expiry_date'] ?? null)
        ->where('location', $data['location'] ?? null)
        ->first();

    // 2. IF DUPLICATE EXISTS: MERGE instead of creating new
    if ($existing) {
        $existing->quantity += $data['quantity'];  // Add quantities together
        $existing->save();
        $existing->load(['ingredient', 'unit']);
        return $existing;
    }

    // 3. IF NO DUPLICATE: Create new inventory item
    $inventory = new Inventory;
    $inventory->ingredient_id = $data['ingredient_id'];
    $inventory->quantity = $data['quantity'];
    $inventory->unit_id = $data['unit_id'];
    $inventory->expiry_date = $data['expiry_date'] ?? null;
    $inventory->location = $data['location'] ?? null;
    $inventory->household_id = $householdId;
    $inventory->save();

    $inventory->load(['ingredient', 'unit']);
    return $inventory;
}
```

**Why This Logic Exists:**
- Prevents duplicate pantry entries
- If user adds "2kg of flour" and then adds "1kg of flour" with same expiry/location
- Instead of 2 separate entries, it becomes 1 entry with 3kg total

#### Very Complex Method: `mergeDuplicates()` - Algorithm

```php
// Lines 169-211
function mergeDuplicates($householdId)
{
    // 1. Get all inventory items, sorted
    $items = Inventory::where('household_id', $householdId)
        ->orderBy('ingredient_id')
        ->orderBy('expiry_date')
        ->orderBy('location')
        ->orderBy('unit_id')
        ->get();

    $merged = [];      // Items to keep
    $toDelete = [];    // IDs of items to delete

    // 2. LOOP THROUGH ALL ITEMS
    foreach ($items as $item) {
        // 3. CREATE UNIQUE KEY for grouping duplicates
        $key = sprintf(
            '%s_%s_%s_%s',
            $item->ingredient_id,
            $item->expiry_date ?? 'null',
            $item->location ?? 'null',
            $item->unit_id
        );

        // 4. IF FIRST TIME SEEING THIS KEY: Keep it
        if (!isset($merged[$key])) {
            $merged[$key] = $item;
        } else {
            // 5. IF DUPLICATE: Merge quantities, mark for deletion
            $merged[$key]->quantity += $item->quantity;
            $merged[$key]->save();
            $toDelete[] = $item->id;
        }
    }

    // 6. DELETE ALL DUPLICATE ITEMS
    if (!empty($toDelete)) {
        Inventory::whereIn('id', $toDelete)->delete();
    }

    return [
        'merged' => count($merged),
        'deleted' => count($toDelete)
    ];
}
```

**Algorithm Explanation:**
1. **Grouping Strategy:** Creates a unique key from ingredient_id + expiry_date + location + unit_id
2. **First Occurrence:** Keeps the first item with each unique key
3. **Subsequent Occurrences:** Adds their quantity to the first one, marks for deletion
4. **Bulk Delete:** Deletes all duplicates in one database query

**Example:**
```
Before:
- Item 1: Flour, 2kg, expires 2024-01-01, Fridge
- Item 2: Flour, 1kg, expires 2024-01-01, Fridge
- Item 3: Flour, 3kg, expires 2024-01-05, Pantry

After:
- Item 1: Flour, 3kg (2+1 merged), expires 2024-01-01, Fridge
- Item 3: Flour, 3kg, expires 2024-01-05, Pantry (different expiry, so not merged)
```

---

### 6. `app/Http/Controllers/RecipeController.php` - Recipe Management

**Purpose:** Handles recipe creation, updates, and ingredient processing.

#### Very Complex Method: `create()` - Ingredient Processing

```php
// Lines 85-216
function create(Request $request)
{
    $user = Auth::user();
    
    // 1. Basic validation
    $request->validate([
        'title' => 'required|string|max:255',
        'instructions' => 'required|string',
        'ingredients' => 'nullable|array',
    ]);

    // 2. PROCESS INGREDIENTS - This is the complex part!
    $processedIngredients = [];
    if ($request->has('ingredients') && is_array($request->ingredients)) {
        foreach ($request->ingredients as $index => $ingredient) {
            $ingredientId = null;
            
            // 3. ACCEPT MULTIPLE INPUT FORMATS
            if (isset($ingredient['ingredient_id'])) {
                // Format 1: User provides ingredient ID directly
                $ingredientId = $ingredient['ingredient_id'];
            } elseif (isset($ingredient['ingredient']) || isset($ingredient['name'])) {
                // Format 2: User provides ingredient name (string)
                $ingredientName = $ingredient['ingredient'] ?? $ingredient['name'];
                
                // 4. LOOK UP OR CREATE INGREDIENT
                $foundIngredient = Ingredient::where('name', $ingredientName)
                    ->where('household_id', $user->household_id)
                    ->first();
                
                if (!$foundIngredient) {
                    // AUTO-CREATE ingredient if it doesn't exist!
                    $foundIngredient = $this->ingredientService->create($user->household_id, [
                        'name' => $ingredientName,
                        'unit_id' => $unitId,
                    ]);
                }
                $ingredientId = $foundIngredient->id;
            }
            
            // 5. HANDLE UNIT (similar complexity)
            $unitId = null;
            if (isset($ingredient['unit_id'])) {
                $unitId = $ingredient['unit_id'];
            } elseif (isset($ingredient['unit'])) {
                // Find or create unit by abbreviation
                $unit = $this->findOrCreateUnit($ingredient['unit']);
                $unitId = $unit->id;
            } else {
                // Default to 'g' (Gram)
                $unit = $this->findOrCreateUnit('g');
                $unitId = $unit->id;
            }
            
            // 6. BUILD PROCESSED ARRAY
            $processedIngredients[] = [
                'ingredient_id' => $ingredientId,
                'quantity' => $ingredient['quantity'],
                'unit_id' => $unitId,
            ];
        }
    }

    // 7. Replace original ingredients with processed ones
    $requestData = $request->all();
    $requestData['ingredients'] = $processedIngredients;

    // 8. Create recipe with processed data
    $recipe = $this->recipeService->create($user->household_id, $requestData);
    return $this->responseJSON($recipe);
}
```

**Why This is Complex:**
1. **Flexible Input:** Accepts ingredient as ID OR name string
2. **Auto-Creation:** Creates ingredients/units if they don't exist (user-friendly)
3. **Multiple Fallbacks:** Tries unit_id → unit abbreviation → ingredient's default unit → 'g'
4. **Data Transformation:** Converts user-friendly input to database-ready format

**Example Input:**
```json
{
  "title": "Pasta",
  "ingredients": [
    {
      "ingredient": "Tomato",      // Name, not ID!
      "quantity": 2,
      "unit": "pieces"             // Abbreviation, not ID!
    }
  ]
}
```

**After Processing:**
```php
[
  'ingredient_id' => 5,    // Found or created "Tomato"
  'quantity' => 2,
  'unit_id' => 8          // Found or created "pieces" unit
]
```

---

### 7. `app/Services/RecipeService.php` - Recipe Business Logic

#### Complex Method: `create()` - Auto-Add to Pantry & Shopping List

```php
// Lines 85-191
function create($householdId, $data)
{
    // 1. Create recipe
    $recipe = new Recipe;
    $recipe->title = $data['title'];
    $recipe->instructions = $data['instructions'];
    $recipe->household_id = $householdId;
    $recipe->save();

    // 2. Attach ingredients to recipe (many-to-many relationship)
    if (isset($data['ingredients']) && is_array($data['ingredients'])) {
        foreach ($data['ingredients'] as $ingredientData) {
            // Verify ingredient belongs to household
            $ingredient = Ingredient::where('id', $ingredientData['ingredient_id'])
                ->where('household_id', $householdId)
                ->first();

            if ($ingredient) {
                // Attach to recipe with pivot data (quantity, unit_id)
                $recipe->ingredients()->attach($ingredientData['ingredient_id'], [
                    'quantity' => $ingredientData['quantity'],
                    'unit_id' => $ingredientData['unit_id'],
                ]);

                // 3. AUTO-ADD TO PANTRY
                try {
                    $this->pantryService->create($householdId, [
                        'ingredient_id' => $ingredientData['ingredient_id'],
                        'quantity' => $ingredientData['quantity'],
                        'unit_id' => $ingredientData['unit_id'],
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Failed to add to pantry: ' . $e->getMessage());
                }

                // 4. AUTO-ADD TO SHOPPING LIST
                try {
                    // Get or create active shopping list
                    $shoppingList = ShoppingList::where('household_id', $householdId)
                        ->where('is_completed', false)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if (!$shoppingList) {
                        $shoppingList = $this->shoppingListService->create($householdId, 'Shopping List - ' . date('Y-m-d'));
                    }

                    // Check for duplicates
                    $existingItem = ShoppingListItem::where('shopping_list_id', $shoppingList->id)
                        ->where('ingredient_id', $ingredientData['ingredient_id'])
                        ->where('unit_id', $ingredientData['unit_id'])
                        ->first();

                    if ($existingItem) {
                        // Merge quantities
                        $existingItem->quantity += $ingredientData['quantity'];
                        $existingItem->save();
                    } else {
                        // Add new item
                        $this->shoppingListService->addItem(
                            $shoppingList->id,
                            $householdId,
                            $ingredientData['ingredient_id'],
                            $ingredientData['quantity'],
                            $ingredientData['unit_id']
                        );
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to add to shopping list: ' . $e->getMessage());
                }
            }
        }
    }

    return $recipe;
}
```

**Why This is Complex:**
1. **Many-to-Many Relationship:** Uses `attach()` to link ingredients to recipes via pivot table
2. **Side Effects:** Creating a recipe automatically:
   - Adds ingredients to pantry
   - Adds ingredients to shopping list
3. **Error Handling:** Uses try-catch so recipe creation doesn't fail if pantry/shopping list operations fail
4. **Duplicate Prevention:** Checks if shopping list item already exists before adding

**Pivot Table Concept:**
```
recipes table:        ingredient_recipe table:        ingredients table:
id | title            recipe_id | ingredient_id | quantity | unit_id    id | name
1  | Pasta            1         | 5             | 2        | 8          5  | Tomato
                                1         | 7             | 500      | 1          7  | Pasta
```

---

### 8. `app/Traits/ResponseTrait.php` - Standardized Responses

**Purpose:** Ensures all API responses have the same format.

```php
// Lines 7-13
protected function responseJSON($payload, $status = "success", $status_code = 200)
{
    return response()->json([
        "status" => $status,
        "payload" => $payload
    ], $status_code);
}
```

**Usage:**
```php
// Success response
return $this->responseJSON($user);
// Returns: { "status": "success", "payload": { user data } }

// Error response
return $this->responseJSON(null, "failure", 404);
// Returns: { "status": "failure", "payload": null } with 404 status code
```

**Why This Exists:**
- Consistent API response format
- Frontend always knows where to find data (`payload` field)
- Easy to check success/failure (`status` field)

---

## 🧩 Complex Code Sections Explained

### 1. **JWT Authentication Flow**

**Where:** `app/Services/AuthService.php` → `login()`

```php
$token = Auth::guard('api')->attempt($credentials);
```

**How It Works:**
1. Laravel checks email/password against database
2. If valid, generates a JWT token (encrypted string containing user ID)
3. Token is returned to frontend
4. Frontend stores token and sends it in `Authorization: Bearer {token}` header
5. Middleware validates token on every protected request

**Why JWT Instead of Sessions:**
- Stateless (server doesn't store session data)
- Works across multiple servers (microservices)
- Mobile-friendly (no cookies needed)

---

### 2. **Eager Loading (N+1 Problem Prevention)**

**Where:** `app/Services/PantryService.php` → `getAll()`

```php
return Inventory::with(['ingredient', 'unit'])
    ->where('household_id', $householdId)
    ->get();
```

**The Problem (Without Eager Loading):**
```php
// BAD: Makes 1 + N queries
$inventory = Inventory::where('household_id', $householdId)->get();
foreach ($inventory as $item) {
    $item->ingredient;  // Query #2, #3, #4... (one per item!)
    $item->unit;        // Query #5, #6, #7...
}
// Total: 1 + (N * 2) queries for N items
```

**The Solution (With Eager Loading):**
```php
// GOOD: Makes only 3 queries total
Inventory::with(['ingredient', 'unit'])->get();
// Query 1: Get all inventory items
// Query 2: Get all related ingredients (one query for all)
// Query 3: Get all related units (one query for all)
// Total: 3 queries regardless of item count
```

---

### 3. **Dynamic Validation Rules**

**Where:** `app/Http/Controllers/PantryController.php` → `update()`

```php
$validationRules = [
    'quantity' => 'nullable|numeric|min:0',
    // ... base rules
];

// Only add uniqueness rule if name is being updated
if ($ingredientName) {
    $validationRules['ingredient_name'] = [
        'nullable',
        'string',
        'max:255',
        \Illuminate\Validation\Rule::unique('ingredients', 'name')
            ->where('household_id', $user->household_id)
            ->ignore($inventory->ingredient_id)
    ];
}
```

**Why Dynamic:**
- If user isn't changing the name, don't validate uniqueness
- Saves unnecessary database queries
- More efficient validation

**Scoped Uniqueness:**
- `->where('household_id', $user->household_id)` means:
  - Ingredient name must be unique WITHIN the household
  - Different households can have ingredients with the same name
- `->ignore($inventory->ingredient_id)` means:
  - When updating, ignore the current ingredient's name
  - Prevents "name already taken" error when name hasn't changed

---

### 4. **Many-to-Many Relationships with Pivot Data**

**Where:** `app/Services/RecipeService.php` → `create()`

```php
$recipe->ingredients()->attach($ingredientData['ingredient_id'], [
    'quantity' => $ingredientData['quantity'],
    'unit_id' => $ingredientData['unit_id'],
]);
```

**Database Structure:**
```
recipes table:
id | title
1  | Pasta

ingredients table:
id | name
5  | Tomato
7  | Pasta

ingredient_recipe table (pivot):
id | recipe_id | ingredient_id | quantity | unit_id
1  | 1         | 5            | 2        | 8
2  | 1         | 7            | 500      | 1
```

**Why Pivot Table:**
- One recipe has many ingredients
- One ingredient can be in many recipes
- Need to store additional data (quantity, unit_id) for each relationship
- Pivot table stores the "many-to-many" relationship + extra data

**Accessing Pivot Data:**
```php
$recipe->ingredients;  // Gets all ingredients
$recipe->ingredients[0]->pivot->quantity;  // Gets quantity from pivot table
```

---

### 5. **Carbon Date Manipulation**

**Where:** `app/Services/PantryService.php` → `getExpiringSoon()`

```php
$now = Carbon::now()->startOfDay();
$expiryDate = Carbon::now()->addDays($days)->endOfDay();

return Inventory::with(['ingredient', 'unit'])
    ->where('household_id', $householdId)
    ->whereNotNull('expiry_date')
    ->where('expiry_date', '<=', $expiryDate)
    ->where('expiry_date', '>=', $now)
    ->orderBy('expiry_date', 'asc')
    ->get();
```

**Explanation:**
- `Carbon::now()->startOfDay()` = Today at 00:00:00
- `Carbon::now()->addDays(7)->endOfDay()` = 7 days from now at 23:59:59
- Query finds items expiring between now and 7 days from now
- `orderBy('expiry_date', 'asc')` = Soonest expiry first

**Why Use Carbon:**
- Handles timezones, daylight saving, leap years
- More readable than raw date strings
- Chainable methods for easy manipulation

---

## 🎯 Key Takeaways

1. **Routes** → **Controllers** → **Services** → **Models** → **Database**
2. **Controllers** handle HTTP (validation, responses)
3. **Services** handle business logic (database operations)
4. **Models** represent database tables and relationships
5. **Middleware** protects routes (authentication, authorization)
6. **Traits** provide reusable code (ResponseTrait for consistent JSON)

---

## 🔍 Where to Find Things

| What You Need | Where to Look |
|---------------|---------------|
| API endpoints | `routes/api.php` |
| Request handling | `app/Http/Controllers/` |
| Business logic | `app/Services/` |
| Database tables | `app/Models/` |
| Database structure | `database/migrations/` |
| Response formatting | `app/Traits/ResponseTrait.php` |
| Authentication | `app/Services/AuthService.php` |

---

## 📚 Additional Resources

- **Laravel Documentation:** https://laravel.com/docs
- **Eloquent Relationships:** https://laravel.com/docs/eloquent-relationships
- **JWT Authentication:** https://jwt-auth.readthedocs.io/
- **Carbon (Dates):** https://carbon.nesbot.com/docs/

---

*This document explains the HomeLife backend codebase structure and complex code sections. For questions, refer to the specific file and line numbers mentioned above.*

