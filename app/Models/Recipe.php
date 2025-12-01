<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    protected $table = 'recipes';
    
    protected $fillable = [
        'title',
        'instructions',
        'tags',
        'servings',
        'prep_time',
        'cook_time',
        'household_id'
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_recipe')
            ->withPivot('quantity', 'unit_id')
            ->withTimestamps();
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }
}

