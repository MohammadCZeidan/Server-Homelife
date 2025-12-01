<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UnitService;

class UnitController extends Controller
{
    private $unitService;

    function __construct(UnitService $unitService)
    {
        $this->unitService = $unitService;
    }

    function getAll()
    {
        $units = $this->unitService->getAll();
        return $this->responseJSON($units);
    }

    function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:units,name',
            'abbreviation' => 'nullable|string|max:10',
        ]);

        $unit = $this->unitService->create($request->all());
        return $this->responseJSON($unit);
    }
}

