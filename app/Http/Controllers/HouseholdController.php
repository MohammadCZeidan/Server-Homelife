<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\HouseholdService;

class HouseholdController extends Controller{
    private $householdService;

    function __construct(HouseholdService $householdService){
        $this->householdService = $householdService;
    }

    function get(){
        $user = Auth::user();
        $household = $this->householdService->getHousehold($user->id);
        
        if (!$household) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($household);
    }

    function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $household = $this->householdService->createHousehold($user->id, $request->name);
        
        return $this->responseJSON($household);
    }

    function join(Request $request){
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = Auth::user();
        $household = $this->householdService->joinHousehold($user->id, $request->code);
        
        if (!$household) {
            return $this->responseJSON(null, "failure", 400);
        }

        return $this->responseJSON($household);
    }

    function generateInvite()
    {
        $user = Auth::user();
        $invite = $this->householdService->generateInvite($user->id);
        
        if (!$invite) {
            return $this->responseJSON(null, "failure", 404);
        }

        return $this->responseJSON($invite);
    }
}

