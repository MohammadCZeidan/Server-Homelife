<?php

namespace App\Services;

use App\Models\Household;
use App\Models\HouseholdInvite;
use App\Models\User;
use Illuminate\Support\Str;

class HouseholdService
{
    function getHousehold($userId)
    {
        $user = User::with('household')->find($userId);
        if (!$user || !$user->household_id) {
            return null;
        }

        return Household::with(['users', 'invites'])->find($user->household_id);
    }

    function createHousehold($userId, $name)
    {
        $household = new Household;
        $household->name = $name;
        $household->save();

        $user = User::find($userId);
        $user->household_id = $household->id;
        $user->save();

        $invite = new HouseholdInvite;
        $invite->household_id = $household->id;
        $invite->code = Str::upper(Str::random(8));
        $invite->expires_at = now()->addDays(30);
        $invite->save();

        $household->load(['users', 'invites']);
        return $household;
    }

    function joinHousehold($userId, $code)
    {
        $invite = HouseholdInvite::where('code', $code)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$invite) {
            return null;
        }

        $user = User::find($userId);
        $user->household_id = $invite->household_id;
        $user->save();

        return Household::with(['users', 'invites'])->find($invite->household_id);
    }

    function generateInvite($userId)
    {
        $user = User::find($userId);
        if (!$user->household_id) {
            return null;
        }

        $invite = new HouseholdInvite;
        $invite->household_id = $user->household_id;
        $invite->code = Str::upper(Str::random(8));
        $invite->expires_at = now()->addDays(30);
        $invite->save();

        return $invite;
    }
}

