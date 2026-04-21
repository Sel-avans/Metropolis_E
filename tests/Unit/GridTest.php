<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Http\Controllers\Grid;

class GridTest extends TestCase
{
    /**
     * Subtask: Generate 12x12 grid.
     * Checks if the controller correctly sets the size.
     */
    public function test_grid_initialization_size(): void
    {
        // Act: Create a grid of 12
        $grid = new Grid(12);

        // Assert: The size property should be 12
        $this->assertEquals(12, $grid->width);
    }
}