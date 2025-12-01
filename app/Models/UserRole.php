<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserRole extends Model
{
    protected $table = 'user_roles';

    protected $fillable = ['role'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'user_role_id');
    }
}

