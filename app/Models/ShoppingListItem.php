<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingListItem extends Model
{
    protected $table = 'shopping_list_items';
    
    protected $fillable = [
        'shopping_list_id',
        'ingredient_id',
        'quantity',
        'unit_id',
        'bought'
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'bought' => 'boolean',
        ];
    }

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}

