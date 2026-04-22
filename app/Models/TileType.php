<?php

namespace App\Models;

enum TileType: string
{
    case EMPTY = 'empty';
    case COMMERCE = 'commerce';
    case COMMUNITY = 'community';
    case CULTURE = 'culture';
}
