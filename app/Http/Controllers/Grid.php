<?php

namespace App\Http\Controllers;


use App\Http\Controllers\TileType;
use App\Http\Controllers\TileMark;


class Grid{

    public $width = 0;
    public $height = 0;
    public $totalCells = 0;
    public const MAX_SIZE = 9;
    private $grid = [[]];


    public function __construct($totalCells = 81){
        // Calculate dimensions from total cells (square root for square grids)
        $this->totalCells = max(min($totalCells, $this::MAX_SIZE * $this::MAX_SIZE), 1);
        $this->width = intval(sqrt($this->totalCells));
        $this->height = intval(ceil($this->totalCells / $this->width));
        
        $gridId = 0;
        for($x = 0; $x < $this->width; $x++){
            for($y = 0; $y < $this->height; $y++){
                if($gridId < $this->totalCells){
                    $this->grid[$x][$y] = new Node($x, $y, $gridId);
                    $gridId++;
                }
            }
        }       
        // echo "Grid created: {$this->width}x{$this->height} ({$this->totalCells} cells)";
    }


    public function getWidth() {
        return $this->width;
    }

    public function getHeight() {
        return $this->height;
    }

    public function paintGrid(){
        echo "<div class='container'>";
        echo "<div class='grid' style='grid-template-columns: repeat({$this->width}, minmax(60px, 1fr)); grid-template-rows: repeat({$this->height}, minmax(60px, 1fr));'>";

        for($x = 0; $x < $this->width; $x++){
            for($y = 0; $y < $this->height; $y++){
                if(isset($this->grid[$x][$y])){
                    $node = $this->grid[$x][$y];
                    $tileType = $node->getTileType()->value;
                    $tileMark = $node->getTileMark()->value;
                    $gridId = $node->getGridId();
                    
                    // HIER ZIT DE MAGIC: data-x='$x' en data-y='$y' zijn toegevoegd!
                    echo "<div class='grid-item tile-$tileType colour-$tileMark' data-grid-id='$gridId' data-x='$x' data-y='$y'>";
                    echo "</div>";
                }
            }
        }

        echo "</div>";
        echo "</div>";
    }
}