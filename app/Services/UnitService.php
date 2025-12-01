<?php

namespace App\Services;

use App\Models\Unit;

class UnitService
{
    function getAll()
    {
        return Unit::orderBy('name')->get();
    }

    function create($data)
    {
        $unit = new Unit;
        $unit->name = $data['name'];
        $unit->abbreviation = $data['abbreviation'] ?? null;
        $unit->save();
        
        return $unit;
    }
}

