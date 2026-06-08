<?php
// Tests voor Un-do action

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\GridCell;
use App\Models\CityFunction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UndoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        for ($r = 1; $r <= 4; $r++) {
            for ($c = 1; $c <= 3; $c++) {
                GridCell::create(['row' => $r, 'col' => $c]);
            }
        }

        CityFunction::create([
            'id' => 1,
            'name' => 'Police',
            'category' => 'veiligheid'
        ]);

        CityFunction::create([
            'id' => 2,
            'name' => 'Fire',
            'category' => 'veiligheid'
        ]);
    }

    public function test_undo_restores_insert()
    {
        $this->post('/grid/update', [
            'old_row' => null,
            'old_col' => null,
            'new_row' => 1,
            'new_col' => 1,
            'function_id' => 1
        ]);

        $this->post('/undo');

        $this->assertNull(GridCell::where('row',1)->where('col',1)->first()->function_id);
    }

    public function test_undo_restores_replace()
    {
        GridCell::where('row',1)->where('col',1)->update(['function_id' => 1]);

        $this->post('/grid/update', [
            'old_row' => null,
            'old_col' => null,
            'new_row' => 1,
            'new_col' => 1,
            'function_id' => 2
        ]);

        $this->post('/undo');

        $this->assertEquals(1, GridCell::where('row',1)->where('col',1)->first()->function_id);
    }

    public function test_undo_restores_remove()
    {
        GridCell::where('row',1)->where('col',1)->update(['function_id' => 1]);

        $this->delete('/grid/cell/1/function');

        $this->post('/undo');

        $this->assertEquals(1, GridCell::where('row',1)->where('col',1)->first()->function_id);
    }

    public function test_undo_restores_move()
    {
        GridCell::where('row',1)->where('col',1)->update(['function_id' => 1]);

        $this->post('/grid/update', [
            'old_row' => 1,
            'old_col' => 1,
            'new_row' => 1,
            'new_col' => 2,
            'function_id' => 1
        ]);

        $this->post('/undo');

        $this->assertEquals(1, GridCell::where('row',1)->where('col',1)->first()->function_id);
        $this->assertNull(GridCell::where('row',1)->where('col',2)->first()->function_id);
    }

    public function test_undo_is_only_one_step()
    {
        $this->post('/grid/update', [
            'old_row' => null,
            'old_col' => null,
            'new_row' => 1,
            'new_col' => 1,
            'function_id' => 1
        ]);

        $this->post('/undo');
        $this->post('/undo');

        $this->assertNull(GridCell::where('row',1)->where('col',1)->first()->function_id);
    }
}
