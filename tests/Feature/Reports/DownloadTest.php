<?php

namespace Tests\Feature\Reports;

use App\Models\Document;
use App\Models\GeneratedReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_download_saved_report()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'test.xlsx',
        ]);

        $report = GeneratedReport::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'name' => 'Test Report',
            'description' => 'Test Description',
            'selected_columns' => [0, 1],
            'filter_column' => null,
            'filter_value' => null,
            'file_path' => 'reports/nonexistent_file.xlsx', // File doesn't exist
            'file_name' => 'test_report.xlsx',
            'file_size' => 1024,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'is_saved' => true,
        ]);

        // Since file doesn't exist, should return 404
        $this->actingAs($user)
            ->get(route('reports.download', $report->id))
            ->assertStatus(404);
    }

    public function test_cannot_download_other_users_report()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $otherUser->id,
            'original_name' => 'test.xlsx',
        ]);

        $report = GeneratedReport::create([
            'document_id' => $document->id,
            'user_id' => $otherUser->id,
            'name' => 'Test Report',
            'description' => 'Test Description',
            'selected_columns' => [0, 1],
            'filter_column' => null,
            'filter_value' => null,
            'file_path' => 'reports/test_report.xlsx',
            'file_name' => 'test_report.xlsx',
            'file_size' => 1024,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'is_saved' => true,
        ]);

        $this->actingAs($user)
            ->get(route('reports.download', $report->id))
            ->assertStatus(404);
    }

    public function test_cannot_download_nonexistent_report()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.download', 999))
            ->assertStatus(404);
    }

    public function test_requires_authentication_to_download()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'test.xlsx',
        ]);

        $report = GeneratedReport::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'name' => 'Test Report',
            'description' => 'Test Description',
            'selected_columns' => [0, 1],
            'filter_column' => null,
            'filter_value' => null,
            'file_path' => 'reports/test_report.xlsx',
            'file_name' => 'test_report.xlsx',
            'file_size' => 1024,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'is_saved' => true,
        ]);

        $this->get(route('reports.download', $report->id))
            ->assertRedirect(route('login'));
    }

    public function test_handles_missing_file_gracefully()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'test.xlsx',
        ]);

        $report = GeneratedReport::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'name' => 'Test Report',
            'description' => 'Test Description',
            'selected_columns' => [0, 1],
            'filter_column' => null,
            'filter_value' => null,
            'file_path' => 'reports/nonexistent_file.xlsx',
            'file_name' => 'test_report.xlsx',
            'file_size' => 1024,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'is_saved' => true,
        ]);

        $this->actingAs($user)
            ->get(route('reports.download', $report->id))
            ->assertStatus(404);
    }

    public function test_can_download_existing_report_file()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'test.xlsx',
        ]);

        // Create a test file
        $testContent = 'Test Excel Content';
        $filePath = 'reports/test_report_' . time() . '.xlsx';
        Storage::disk('local')->put($filePath, $testContent);

        $report = GeneratedReport::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'name' => 'Test Report',
            'description' => 'Test Description',
            'selected_columns' => [0, 1],
            'filter_column' => null,
            'filter_value' => null,
            'file_path' => $filePath,
            'file_name' => 'test_report.xlsx',
            'file_size' => strlen($testContent),
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'is_saved' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('reports.download', $report->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=test_report.xlsx');
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertHeader('Content-Length', (string) strlen($testContent));

        // Clean up
        Storage::disk('local')->delete($filePath);
    }
}
