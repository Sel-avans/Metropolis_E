<?php

namespace App\Models;

class Node
{
    private int $x;
    private int $y;
    private int $gridId;
    private TileType $tileType;
    private TileMark $tileMark;

    public function __construct(int $x, int $y, int $gridId)
    {
        $this->x = $x;
        $this->y = $y;
        $this->gridId = $gridId;
        $this->tileType = TileType::EMPTY;  
        $this->tileMark = TileMark::UNMARKED; 
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function getGridId(): int
    {
        return $this->gridId;
    }

    public function getTileType(): TileType
    {
        return $this->tileType;
    }

    public function setTileType(TileType $tileType): void
    {
        $this->tileType = $tileType;
    }

    public function getTileMark(): TileMark
    {
        return $this->tileMark;
    }

    public function setTileMark(TileMark $tileMark): void
    {
        $this->tileMark = $tileMark;
    }
}
