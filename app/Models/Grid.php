<?php

namespace App\Models;

require_once __DIR__ . "/Node.php";
use App\Http\Controllers\TileType;
use App\Http\Controllers\TileMark;

class Grid
{
    private int $width = 0;
    private int $height = 0;
    private int $totalCells = 0;
    private const MAX_SIZE = 9;
    private array $grid = [[]];

    public function __construct(int $totalCells = 81)
    {
        $this->totalCells = max(min($totalCells, self::MAX_SIZE * self::MAX_SIZE), 1);
        $this->width = intval(sqrt($this->totalCells));
        $this->height = intval(ceil($this->totalCells / $this->width));

        $gridId = 0;
        for ($x = 0; $x < $this->width; $x++) {
            for ($y = 0; $y < $this->height; $y++) {
                if ($gridId < $this->totalCells) {
                    $this->grid[$x][$y] = new Node($x, $y, $gridId);
                    $gridId++;
                }
            }
        }
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function paintGrid(string $name = "Default grid"): string
    {
        $html = "<div class='container'>";
        $html .= "<h3 class='grid-header'>$name</h3>";
        $html .= "<div class='grid' style='grid-template-columns: repeat({$this->width}, minmax(60px, 1fr)); grid-template-rows: repeat({$this->height}, minmax(60px, 1fr));'>";

        for ($y = 0; $y < $this->height; $y++) { 
            for ($x = 0; $x < $this->width; $x++) { 
                if (isset($this->grid[$x][$y])) {
                    $node = $this->grid[$x][$y];
                    $tileType = $node->getTileType()->value;
                    $tileMark = $node->getTileMark()->value;
                    $gridId = $node->getGridId();
                    $html .= "<div class='grid-item tile-$tileType colour-$tileMark' data-grid-id='$gridId' role='gridcell' aria-label='Tile $tileType'></div>";
                } else {
                    $html .= "<div class='grid-item empty' data-grid-id='{$x}-{$y}' role='gridcell' aria-label='Empty cell'></div>";
                }
            }
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}
