<?php

namespace App\Policies;

use App\Models\User;
use App\Enums\UserRole;

class PagePolicy
{
    //Machtigingen meerdere rollen
    public function ViewGridPage(User $user): bool
    {
        return $user->role === UserRole::City_planner;
    }

    public function ViewEditPage(User $user): bool
    {
        return $user->role === UserRole::City_planner;
    }

    public function ViewEffectsPage(User $user): bool
    {
        return $user->role === UserRole::City_planner;
    }

    public function ViewConditionsPage(User $user): bool
    {
        return $user->role === UserRole::City_planner;
    }

    //City planner en Muncipal policy maker
    public function ApproveGrid(User $user): bool
    {
        return $user->role === UserRole::Municipal_Policy_Maker;
    }


    //Machtigingen City Planner
    public function PlaceFunctions(User $user): bool
    {
        return $user->role === UserRole::City_planner;
    }

    

    public function ViewEditPage(User $user): bool
    {
        return $user->role === UserRole::City_planner;
    }

    //Machtigingen Administrator

    //Machtigingen Municipal Policy Maker


    public function ApproveGrid(User $user): bool
    {
        return $user->role === UserRole::Municipal_Policy_Maker;
    }

}
