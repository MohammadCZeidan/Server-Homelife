<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meal extends Model
{
    protected $table = 'meals';
    
    protected $fillable = [
        'week_id',
        'day',
        'slot',
        'recipe_id'
    ];

    public function week(): BelongsTo
    {
        return $this->belongsTo(Week::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}

