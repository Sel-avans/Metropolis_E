<?php

namespace App\Enums;

enum UserRole: string 
{

    case City_planner = 'City Planner';
    case Administrator = 'Administrator';
    case Municipal_Policy_Maker = 'Municipal Policy Maker';
}