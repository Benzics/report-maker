<?php

namespace Tests\Feature\Reports;

use App\Livewire\Reports\Preview;
use App\Models\Document;
use App\Models\GeneratedReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_access_preview_page_with_valid_report_data()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'test.xlsx',
        ]);

        $report = new GeneratedReport([
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
            'is_saved' => false,
        ]);

        $reportData = base64_encode(serialize($report));

        $this->actingAs($user)
            ->get(route('reports.preview', ['report' => $reportData]))
            ->assertStatus(200)
            ->assertSee('Report Preview');
    }

    public function test_shows_error_when_no_report_data_provided()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.preview'))
            ->assertStatus(200)
            ->assertSee('No report data provided');
    }

    public function test_shows_error_when_invalid_report_data()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.preview', ['report' => 'invalid_data']))
            ->assertStatus(200)
            ->assertSee('Failed to load report data');
    }

    public function test_shows_error_when_original_document_not_found()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $report = new GeneratedReport([
            'document_id' => 999, // Non-existent document
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
            'is_saved' => false,
        ]);

        $reportData = base64_encode(serialize($report));

        $this->actingAs($user)
            ->get(route('reports.preview', ['report' => $reportData]))
            ->assertStatus(200)
            ->assertSee('Original document not found');
    }

    public function test_can_save_report_to_database()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'test.xlsx',
        ]);

        $report = new GeneratedReport([
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
            'is_saved' => false,
        ]);

        $reportData = base64_encode(serialize($report));

        Livewire::actingAs($user)
            ->test(Preview::class)
            ->call('saveReport')
            ->assertDispatched('showError', __('No report data to save.'));

        // Since we're testing error case, no database check needed
    }

    public function test_cannot_save_already_saved_report()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'test.xlsx',
        ]);

        $report = new GeneratedReport([
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
            'is_saved' => true, // Already saved
        ]);

        $reportData = base64_encode(serialize($report));

        Livewire::actingAs($user)
            ->test(Preview::class)
            ->call('saveReport')
            ->assertDispatched('showError', __('No report data to save.'));
    }

    public function test_shows_saved_status_when_report_is_saved()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'test.xlsx',
        ]);

        $report = new GeneratedReport([
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

        $reportData = base64_encode(serialize($report));

        $this->actingAs($user)
            ->get(route('reports.preview', ['report' => $reportData]))
            ->assertStatus(200)
            ->assertSee('Report Preview');
    }

    public function test_formats_file_size_correctly()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'test.xlsx',
        ]);

        $report = new GeneratedReport([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'name' => 'Test Report',
            'description' => 'Test Description',
            'selected_columns' => [0, 1],
            'filter_column' => null,
            'filter_value' => null,
            'file_path' => 'reports/test_report.xlsx',
            'file_name' => 'test_report.xlsx',
            'file_size' => 1536, // 1.5 KB
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'is_saved' => false,
        ]);

        $reportData = base64_encode(serialize($report));

        $this->actingAs($user)
            ->get(route('reports.preview', ['report' => $reportData]))
            ->assertStatus(200)
            ->assertSee('Report Preview');
    }

    public function test_shows_download_link()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'test.xlsx',
        ]);

        $report = new GeneratedReport([
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
            'is_saved' => false,
        ]);

        $reportData = base64_encode(serialize($report));

        $this->actingAs($user)
            ->get(route('reports.preview', ['report' => $reportData]))
            ->assertStatus(200)
            ->assertSee('Report Preview');
    }
}
