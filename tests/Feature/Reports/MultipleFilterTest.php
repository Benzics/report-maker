<?php

namespace Tests\Feature\Reports;

use App\Livewire\Reports\Generate;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MultipleFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_set_multiple_filter_columns_and_values()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'test.xlsx',
            'path' => 'documents/test.xlsx',
            'disk' => 'local',
        ]);

        // Create a test Excel file with sample data
        $this->createTestExcelFile();

        $component = Livewire::actingAs($user)
            ->test(Generate::class, ['documentId' => $document->id]);

        // Wait for columns to load
        $component->call('loadDocumentColumns');

        // Verify columns are loaded
        $this->assertNotEmpty($component->get('columns'));

        // Set multiple filters
        $component->set('filterColumn', '0')
            ->set('filterValue', 'Test')
            ->set('filterColumn2', '1')
            ->set('filterValue2', 'Value')
            ->set('filterColumn3', '2')
            ->set('filterValue3', 'Data');

        // Verify all filter values are set
        $this->assertEquals('0', $component->get('filterColumn'));
        $this->assertEquals('Test', $component->get('filterValue'));
        $this->assertEquals('1', $component->get('filterColumn2'));
        $this->assertEquals('Value', $component->get('filterValue2'));
        $this->assertEquals('2', $component->get('filterColumn3'));
        $this->assertEquals('Data', $component->get('filterValue3'));
    }

    public function test_validation_requires_values_for_all_selected_filter_columns()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'test.xlsx',
            'path' => 'documents/test.xlsx',
            'disk' => 'local',
        ]);

        $this->createTestExcelFile();

        $component = Livewire::actingAs($user)
            ->test(Generate::class, ['documentId' => $document->id]);

        $component->call('loadDocumentColumns');

        // Select columns first
        $component->set('selectedColumns', [0, 1]);

        // Set filter columns but leave one value empty
        $component->set('filterColumn', '0')
            ->set('filterValue', 'Test')
            ->set('filterColumn2', '1')
            ->set('filterValue2', '') // Empty value
            ->set('filterColumn3', '2')
            ->set('filterValue3', 'Data');

        // Try to generate report - should show validation error
        $component->call('generateReport');

        // Should show validation error for missing filter value
        $this->assertNotEmpty($component->get('validationError'));
        $this->assertStringContainsString('second filter column', $component->get('validationError'));
    }

    public function test_can_generate_report_with_multiple_filters()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'test.xlsx',
            'path' => 'documents/test.xlsx',
            'disk' => 'local',
        ]);

        $this->createTestExcelFile();

        $component = Livewire::actingAs($user)
            ->test(Generate::class, ['documentId' => $document->id]);

        $component->call('loadDocumentColumns');

        // Select columns and set multiple filters
        $component->set('selectedColumns', [0, 1, 2])
            ->set('filterColumn', '0')
            ->set('filterValue', 'Test')
            ->set('filterColumn2', '1')
            ->set('filterValue2', 'Value');

        // Generate report should not throw errors
        $component->call('generateReport');

        // Should not have validation errors
        $this->assertEmpty($component->get('validationError'));
    }

    private function createTestExcelFile(): void
    {
        $testData = [
            ['Name', 'Category', 'Status'],
            ['Test Product 1', 'Electronics', 'Active'],
            ['Test Product 2', 'Clothing', 'Inactive'],
            ['Test Product 3', 'Electronics', 'Active'],
        ];

        $filePath = storage_path('app/documents/test.xlsx');

        // Create directory if it doesn't exist
        if (! file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        // Create a simple Excel file using PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $row = 1;
        foreach ($testData as $data) {
            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue($col.$row, $value);
                $col++;
            }
            $row++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filePath);
    }
}
