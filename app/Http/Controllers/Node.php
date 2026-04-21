<?php

namespace App\Http\Controllers;

use App\Http\Controllers\TileMark;
use App\Http\Controllers\TileType;

class Node{
    private $x;
    private $y;
    private $gridId;
    private $type;
    private $mark = TileMark::UNMARKED;

    public function __construct($x, $y, $gridId, $tileType = TileType::EMPTY) {
        $this->x = $x;
        $this->y = $y;
        $this->gridId = $gridId;
        $this->type = $tileType;
    }

    public function getTileMark(): TileMark {
        return $this->mark;
    }

    public function getGridId() {
        return $this->gridId;
    }

    public function getTileType(): TileType  {
        return $this->type;
    }

}