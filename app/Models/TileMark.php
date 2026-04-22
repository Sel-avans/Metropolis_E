<?php

namespace App\Models;

enum TileMark: string
{
    case UNMARKED = 'unmarked';
    case PATH = 'path';
    case ORIGINPATH = 'originpath';
    case DESTINATIONPATH = 'destinationpath';
    case BLOCKED = 'blocked';
    case CALCPATH = 'calcpath';
}
