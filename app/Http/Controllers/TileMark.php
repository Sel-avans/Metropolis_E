<?php

namespace App\Http\Controllers;

enum TileMark: String {
    case UNMARKED = "unmarked";
    case PATH = "path";
    case ORIGINPATH = "originpath";
    case DESTINATIONPATH = "destinationpath";
    case BLOCKED = "blocked";
    case CALCPATH = "calcpath";
}