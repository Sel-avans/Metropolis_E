<?php

namespace App\Policies;

use App\Models\User;
use App\Enums\UserRole;

class PagePolicy
{
    //Machtigingen meerdere rollen
    public function CanViewGridPage(User $user): bool
    {
        return in_array($user->role, [
            UserRole::City_planner,
            UserRole::Administrator,
            UserRole::Municipal_Policy_Maker
        ]);
    }

    public function CanViewFunctionPage(User $user): bool
    {
        return in_array($user->role, [
            UserRole::Administrator
        ]);
    }

    public function CanViewEffectsPage(User $user): bool
    {
        return in_array($user->role, [
            UserRole::Administrator
        ]);
    }

    public function CanViewConditionsPage(User $user): bool
    {
        return in_array($user->role, [
            UserRole::Administrator,
        ]);
    }

    public function CanViewDashboard(User $user): bool
    {
        return in_array($user->role, [
            UserRole::City_planner,
            UserRole::Administrator,
        ]);
    }

    public function CanViewProfile(User $user): bool
    {
        return in_array($user->role, [
            UserRole::City_planner,
            UserRole::Administrator,
        ]);
    }

    //City planner en Muncipal policy maker
    public function CanApproveGrid(User $user): bool
    {
        return in_array($user->role, [
            UserRole::City_planner,
            UserRole::Municipal_Policy_Maker
        ]);
    }

        public function CanMakeComments(User $user): bool
    {
        return in_array($user->role, [
            UserRole::City_planner,
            UserRole::Municipal_Policy_Makerm,
            UserRole::Administrator
        ]);
    }

    //Machtigingen City Planner
    public function CanPlaceFunctions(User $user): bool
    {
        return  in_array($user->role, [
            UserRole::City_planner,
            UserRole::Administrator
            ]);
    }

    public function CanModifyFunction(User $user): bool
    {
        return $user->role === UserRole::City_planner;
    }

    public function CanAddFunction(User $user): bool
    {
        return $user->role === UserRole::City_planner;
    }

    public function CanDeleteFunction(User $user): bool
    {
        return $user->role === UserRole::City_planner;
    }

    
    //Machtigingen Administrator
    public function CanChangeQOLEffect(User $user): bool
    {
        return $user->role === UserRole::Administrator;
    }

    public function CanEditConditions(User $user): bool
    {
        return $user->role === UserRole::Administrator;
    }

    public function CanAddNewConditions(User $user): bool
    {
        return $user->role === UserRole::Administrator;
    }

        public function CanDeleteConditions(User $user): bool
    {
        return $user->role === UserRole::Administrator;
    }


    //Machtigingen Municipal Policy Maker
        public function canLockFunctions(User $user): bool
    {
        return $user->role === UserRole::Municipal_Policy_Maker;
    }

    

}
