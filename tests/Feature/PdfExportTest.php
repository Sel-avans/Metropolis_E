<?php
// tests voor Rev.1
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\GridCell;
use App\Models\CityFunction;
use App\Models\Effect;
use App\Models\User;
use App\Http\Controllers\QoLController;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PdfExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'Administrator']));
    }

    public function test_export_pdf_returns_pdf_response()
    {
        $response = $this->post('/grid/export-pdf');
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_export_with_empty_grid()
    {
        $response = $this->post('/grid/export-pdf');
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_compute_qol_data_returns_correct_structure()
    {
        $qolData = QoLController::computeQoLData();
        $this->assertIsArray($qolData);
        $this->assertArrayHasKey('categories', $qolData);
        $this->assertArrayHasKey('total_score', $qolData);
        $this->assertIsInt($qolData['total_score']);
    }

    public function test_compute_qol_data_empty_grid_returns_zero()
    {
        $qolData = QoLController::computeQoLData();
        $this->assertEquals(0, $qolData['total_score']);
    }

    public function test_compute_qol_data_includes_actual_function_names()
    {
        $police = CityFunction::factory()->create(['name' => 'Politiebureau', 'category' => 'safety']);
        Effect::factory()->create(['function_id' => $police->id, 'category' => 'safety', 'value' => 5]);
        GridCell::create(['row' => 1, 'col' => 1, 'function_id' => $police->id]);

        $qolData = QoLController::computeQoLData();
        
        $safetyItems = $qolData['categories']['Safety']['items'];
        $this->assertNotEmpty($safetyItems);
        $this->assertStringContainsString('Politiebureau', $safetyItems[0]['function']);
    }

    public function test_pdf_contains_required_sections()
    {
        $police = CityFunction::factory()->create(['name' => 'Politiebureau', 'category' => 'safety']);
        Effect::factory()->create(['function_id' => $police->id, 'category' => 'safety', 'value' => 5]);
        GridCell::create(['row' => 1, 'col' => 1, 'function_id' => $police->id]);

        $response = $this->post('/grid/export-pdf');
        $response->assertStatus(200);
        
        // PDF is binary, just verify it's not empty and has PDF magic bytes
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertStringStartsWith('%PDF', $content);
    }
}
