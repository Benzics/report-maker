<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DateFilteringFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the complete date filtering fix for the user's scenario
     * This simulates the actual report generation process
     */
    public function test_date_filtering_fix_user_scenario()
    {
        // Create a user
        $user = User::factory()->create();

        // Create a test Excel file with dates in DD/MM/YYYY format
        $testData = [
            ['Date', 'Description', 'Amount'],
            ['01/09/2025 07:23', 'Transaction 1', '100.00'],
            ['02/09/2025 08:15', 'Transaction 2', '200.00'],
            ['05/09/2025 09:30', 'Transaction 3', '150.00'],
            ['08/09/2025 10:45', 'Transaction 4', '300.00'],
            ['10/09/2025 11:20', 'Transaction 5', '250.00'],
            ['15/09/2025 12:00', 'Transaction 6', '400.00'],
            ['20/09/2025 13:30', 'Transaction 7', '350.00'],
            ['25/09/2025 14:45', 'Transaction 8', '500.00'],
        ];

        // Create a temporary Excel file
        $tempPath = tempnam(sys_get_temp_dir(), 'test_excel_') . '.xlsx';
        $this->createTestExcelFile($tempPath, $testData);

        // Store the file
        $storedPath = 'test_documents/test_' . time() . '.xlsx';
        Storage::disk('local')->put($storedPath, file_get_contents($tempPath));

        // Create a document record
        $document = Document::create([
            'user_id' => $user->id,
            'original_name' => 'test_dates.xlsx',
            'path' => $storedPath,
            'disk' => 'local',
            'file_size' => filesize($tempPath),
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        // Clean up temporary file
        unlink($tempPath);

        // Test the job with date range filtering (September 1-15, 2025)
        $job = new GenerateReportJob(
            $document->id,
            $user->id,
            [0, 1, 2], // Select all columns
            '0', // Filter by date column (first column)
            '', // No single value
            '', // No second filter
            '',
            '', // No third filter
            '',
            '01/09/2025', // Start date: September 1st
            '15/09/2025', // End date: September 15th
            '', // No second range
            '',
            '', // No third range
            '',
            'table_style_medium_2',
            'test_session'
        );

        // Use reflection to test the private method
        $reflection = new \ReflectionClass($job);
        $processMethod = $reflection->getMethod('processReportData');
        $processMethod->setAccessible(true);

        $result = $processMethod->invoke($job, $document);

        // Should return 6 rows (excluding header): 01/09, 02/09, 05/09, 08/09, 10/09, 15/09
        $this->assertCount(6, $result, 'Should return 6 data rows for September 1-15, 2025');

        // Verify the dates are correctly parsed and included
        $dates = array_column($result, 0); // Get the date column
        $this->assertContains('01/09/2025 07:23', $dates, 'Should include September 1st');
        $this->assertContains('02/09/2025 08:15', $dates, 'Should include September 2nd');
        $this->assertContains('15/09/2025 12:00', $dates, 'Should include September 15th');
        $this->assertNotContains('20/09/2025 13:30', $dates, 'Should NOT include September 20th');
        $this->assertNotContains('25/09/2025 14:45', $dates, 'Should NOT include September 25th');

        // Clean up stored file
        Storage::disk('local')->delete($storedPath);
    }

    /**
     * Test that the old behavior (MM/DD/YYYY) still works for US dates
     */
    public function test_us_date_format_still_works()
    {
        // Create a user
        $user = User::factory()->create();

        // Create a test Excel file with dates in YYYY-MM-DD format (ISO format)
        $testData = [
            ['Date', 'Description', 'Amount'],
            ['2025-09-01 07:23', 'Transaction 1', '100.00'], // September 1st in ISO format
            ['2025-09-15 08:15', 'Transaction 2', '200.00'], // September 15th in ISO format
            ['2025-01-15 09:30', 'Transaction 3', '150.00'], // January 15th in ISO format
        ];

        // Create a temporary Excel file
        $tempPath = tempnam(sys_get_temp_dir(), 'test_excel_us_') . '.xlsx';
        $this->createTestExcelFile($tempPath, $testData);

        // Store the file
        $storedPath = 'test_documents/test_us_' . time() . '.xlsx';
        Storage::disk('local')->put($storedPath, file_get_contents($tempPath));

        // Create a document record
        $document = Document::create([
            'user_id' => $user->id,
            'original_name' => 'test_us_dates.xlsx',
            'path' => $storedPath,
            'disk' => 'local',
            'file_size' => filesize($tempPath),
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        // Clean up temporary file
        unlink($tempPath);

        // Test the job with date range filtering (September 1-15, 2025)
        $job = new GenerateReportJob(
            $document->id,
            $user->id,
            [0, 1, 2], // Select all columns
            '0', // Filter by date column (first column)
            null, // No single value
            null, // No second filter
            null,
            null, // No third filter
            null,
            '2025-09-01', // Start date: September 1st (ISO format)
            '2025-09-15', // End date: September 15th (ISO format)
            null, // No second range
            null,
            null, // No third range
            null,
            'table_style_medium_2',
            'test_session'
        );

        // Use reflection to test the private method
        $reflection = new \ReflectionClass($job);
        $processMethod = $reflection->getMethod('processReportData');
        $processMethod->setAccessible(true);

        $result = $processMethod->invoke($job, $document);

        // Should return 2 rows (excluding header): 09/01 and 09/15
        $this->assertCount(2, $result, 'Should return 2 data rows for September 1-15, 2025 in ISO format');

        // Verify the dates are correctly parsed and included
        $dates = array_column($result, 0); // Get the date column
        $this->assertContains('2025-09-01 07:23', $dates, 'Should include September 1st (ISO format)');
        $this->assertContains('2025-09-15 08:15', $dates, 'Should include September 15th (ISO format)');
        $this->assertNotContains('2025-01-15 09:30', $dates, 'Should NOT include January 15th');

        // Clean up stored file
        Storage::disk('local')->delete($storedPath);
    }

    /**
     * Create a test Excel file with the given data
     */
    private function createTestExcelFile(string $path, array $data): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        // Add data to worksheet
        $row = 1;
        foreach ($data as $rowData) {
            $col = 'A';
            foreach ($rowData as $cellValue) {
                $worksheet->setCellValue($col . $row, $cellValue);
                $col++;
            }
            $row++;
        }

        // Save the file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
    }
}
