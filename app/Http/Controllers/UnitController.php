<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\UnitService;

class UnitController extends Controller
{
    private UnitService $unitService;

    public function __construct(UnitService $unitService)
    {
        $this->unitService = $unitService;
    }

    public function getAll(): JsonResponse
    {
        $units = $this->unitService->getAll();
        return $this->responseJSON($units);
    }

    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:units,name',
            'abbreviation' => 'nullable|string|max:10',
        ]);

        $unit = $this->unitService->create($request->all());
        return $this->responseJSON($unit);
    }
}

