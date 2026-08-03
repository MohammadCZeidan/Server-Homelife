<img src="./readme/card-titles/title1.svg"/>
<br>

## License

This project uses the MIT license inherited from the Laravel application setup.

<br><br>
<!-- project overview -->
<img src="./readme/card-titles/title2.svg"/>

> HomeLife Server is a Laravel API backend for household food planning, pantry management, recipes, shopping lists, expenses, nutrition, and weekly insights.<br>
> It supports JWT-protected users, household membership, pantry inventory, meal planning, shopping workflows, and analytics routes for a home management client application.

<br>
<!-- System Design -->
<img src="./readme/card-titles/title3.svg"/>

### Application Architecture

| Layer | Purpose |
|------|---------|
| **Laravel 12 API** | Versioned REST-style routes under `/api/v0.1` |
| **Auth Layer** | Register, login, logout, refresh, profile update, and current user endpoints |
| **Household Layer** | Household creation, joining, invite generation, and access validation |
| **Domain Services** | Pantry, recipes, meal plans, shopping lists, expenses, nutrition, insights, ingredients, and units |
| **Middleware** | JWT auth, admin-only access, household-required checks, and household access validation |
| **Database Layer** | Eloquent models and migrations for the HomeLife domain |

<br>

### Repository Map

| Path | Description |
|------|-------------|
| `routes/api.php` | Versioned API route map |
| `app/Http/Controllers/` | Auth and domain controllers |
| `app/Http/Middleware/` | Admin and household access guards |
| `app/Services/` | Business logic for each domain area |
| `app/Models/` | Eloquent models for users, households, pantry, recipes, meals, expenses, and more |
| `database/migrations/` | Schema for users, households, inventory, recipes, meals, shopping lists, expenses, AI responses, and cache |
| `composer.json` | Laravel/PHP dependencies and scripts |
| `package.json` | Vite/Tailwind asset tooling |
| `HomeLife.code-workspace` | Editor workspace file |

<br><br>
<!-- Project Highlights -->
<img src="./readme/card-titles/title4.svg"/>

### Core Features

- **JWT authentication**: Register, login, logout, refresh token, profile update, and current-user route.<br>
- **Admin visibility**: Admin-only route for listing users.<br>
- **Household management**: Create household, join household, and generate household invites.<br>
- **Pantry inventory**: Add, update, delete, consume, merge duplicates, update expiry dates, and list expiring items.<br>
- **Recipe management**: Create recipes, update recipes, delete recipes, suggest recipes from pantry, and fetch substitutions.<br>
- **Meal planning**: Generate weekly plans and add/remove meals from a week.<br>
- **Shopping lists**: Create lists, manage items, and generate lists from meal plans.<br>
- **Expense tracking**: Record grocery/household expenses and retrieve summaries.<br>
- **Nutrition and insights**: Calculate recipe/week nutrition and return weekly insight analytics.<br>

<br>

### Domain Model

| Model | Purpose |
|------|---------|
| `User` / `UserRole` | Authentication and role behavior |
| `Household` / `HouseholdInvite` | Shared home membership and invites |
| `Inventory` | Pantry stock records |
| `Ingredient` / `Unit` | Ingredient catalog and measurement units |
| `Recipe` | Recipe definitions and ingredient relationships |
| `Week` / `Meal` | Weekly meal plan structure |
| `ShoppingList` / `ShoppingListItem` | Shopping workflow and list items |
| `Expense` | Household expense tracking |

<br>
<!-- Demo -->
<img src="./readme/card-titles/title5.svg"/>

### Quick Start

Install PHP dependencies:

```bash
composer install
```

Create the environment file:

```bash
cp .env.example .env
```

Generate the app key:

```bash
php artisan key:generate
```

Run migrations:

```bash
php artisan migrate
```

Start the API server:

```bash
php artisan serve
```

Run Vite when asset development is needed:

```bash
npm install
npm run dev
```

<br>

### Main API Groups

All API routes are versioned under:

```text
/api/v0.1
```

| Group | Example Routes | Access |
|------|----------------|--------|
| Auth | `/auth/register`, `/auth/login`, `/auth/me`, `/auth/logout`, `/auth/refresh` | Public/JWT |
| Users | `/users` | Admin only |
| Household | `/household`, `/household/join`, `/household/invite` | JWT |
| Pantry | `/pantry`, `/pantry/expiring`, `/pantry/{id}/consume`, `/pantry/merge-duplicates` | JWT + household |
| Recipes | `/recipes`, `/recipes/suggestions`, `/recipes/{id}/substitutions` | JWT + household |
| Meal Plans | `/meal-plans`, `/meal-plans/{weekId}/meals` | JWT + household |
| Shopping Lists | `/shopping-lists`, `/shopping-lists/{id}/items`, `/shopping-lists/generate` | JWT + household |
| Expenses | `/expenses`, `/expenses/summary` | JWT + household |
| Ingredients | `/ingredients` | Public |
| Units | `/units` | JWT |
| Nutrition | `/nutrition/recipes/{recipeId}`, `/nutrition/weeks/{weekId}` | JWT + household |
| Insights | `/insights/weekly` | JWT + household |

<br>

### Example Request

Register a user:

```bash
curl -X POST http://localhost:8000/api/v0.1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Demo User",
    "email": "demo@example.com",
    "password": "secret123"
  }'
```

Create a pantry item with a bearer token:

```bash
curl -X POST http://localhost:8000/api/v0.1/pantry \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "ingredient_id": 1,
    "quantity": 2,
    "unit_id": 1,
    "expiry_date": "2026-01-15"
  }'
```

<br><br>
<!-- Development & Testing -->
<img src="./readme/card-titles/title6.svg"/>

### Development Commands

| Command | Purpose |
|---------|---------|
| `composer install` | Install PHP dependencies |
| `php artisan serve` | Run the Laravel development server |
| `php artisan migrate` | Apply database migrations |
| `php artisan test` | Run Laravel tests |
| `composer test` | Clear config and run tests via Composer script |
| `composer dev` | Run server, queue listener, logs, and Vite concurrently |
| `npm run dev` | Run Vite dev server |
| `npm run build` | Build frontend assets |

<br>

### Tech Stack

| Tool | Purpose |
|------|---------|
| **Laravel 12** | PHP API framework |
| **PHP 8.2+** | Backend runtime |
| **Eloquent** | ORM and relationships |
| **Laravel Migrations** | Database schema management |
| **JWT Guard** | Token-protected API access through `auth:api` |
| **Vite** | Asset build/dev tooling |
| **Tailwind CSS** | Styling toolkit included in the Laravel setup |
| **PHPUnit** | Test runner |
| **Laravel Pint** | Code style formatting |

<br>

### Database Areas

| Area | Migration Coverage |
|------|--------------------|
| Users and roles | Users table, user roles table, user household foreign key |
| Households | Households and household invites |
| Pantry | Units, ingredients, inventory |
| Recipes | Recipes and ingredient-recipe pivot table |
| Meal planning | Weeks and meals |
| Shopping | Shopping lists and shopping list items |
| Finance | Expenses |
| AI/analytics support | AI responses table, cache table |

<br><br>
<!-- Extras -->
<img src="./readme/card-titles/title7.svg"/>

### Implementation Notes

| Item | Status |
|------|--------|
| API versioning | Routes grouped under `/api/v0.1` |
| Auth routes | Register, login, logout, refresh, me, profile update |
| Household gating | `household.required` middleware used across household domain routes |
| Admin gating | `admin.only` middleware for user listing |
| Public catalogs | Ingredients are public; units require auth |
| Legacy compatibility | Pantry delete supports both `DELETE /{id}` and `POST /{id}/delete` |
| n8n hook | Pantry route can send expiring item email via n8n-oriented workflow |

<br>

---

**HomeLife Server** - Laravel backend for pantry, recipes, meal planning, shopping lists, expenses, nutrition, and household insights.

*Everything a household needs, organized behind one API.*
