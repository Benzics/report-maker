<?php

namespace Tests\Feature\Reports;

use App\Models\Document;
use App\Models\GeneratedReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ViewSavedTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_saved_reports_page(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id]);
        
        $response = $this->actingAs($user)
            ->get(route('reports.saved', $document->id));

        $response->assertStatus(200);
    }

    public function test_cannot_view_other_users_document_reports(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $otherUser->id]);
        
        $response = $this->actingAs($user)
            ->get(route('reports.saved', $document->id));

        $response->assertStatus(404);
    }

    public function test_displays_generated_reports_for_document(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id]);
        
        $report1 = GeneratedReport::factory()->create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'name' => 'Test Report 1',
            'description' => 'Test Description 1',
        ]);
        
        $report2 = GeneratedReport::factory()->create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'name' => 'Test Report 2',
            'description' => 'Test Description 2',
        ]);

        Livewire::actingAs($user)
            ->test('reports.view-saved', ['documentId' => $document->id])
            ->assertSee('Test Report 1')
            ->assertSee('Test Description 1')
            ->assertSee('Test Report 2')
            ->assertSee('Test Description 2');
    }

    public function test_search_functionality_works(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id]);
        
        GeneratedReport::factory()->create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'name' => 'Sales Report',
            'description' => 'Monthly sales data',
        ]);
        
        GeneratedReport::factory()->create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'name' => 'Inventory Report',
            'description' => 'Stock levels',
        ]);

        Livewire::actingAs($user)
            ->test('reports.view-saved', ['documentId' => $document->id])
            ->set('search', 'Sales')
            ->assertSee('Sales Report')
            ->assertDontSee('Inventory Report');
    }

    public function test_can_delete_report(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id]);
        
        $report = GeneratedReport::factory()->create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'name' => 'Test Report',
        ]);

        Livewire::actingAs($user)
            ->test('reports.view-saved', ['documentId' => $document->id])
            ->call('deleteReport', $report->id)
            ->assertDispatched('showSuccess', 'Report deleted successfully.');

        $this->assertDatabaseMissing('generated_reports', ['id' => $report->id]);
    }

    public function test_cannot_delete_other_users_report(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id]);
        
        $report = GeneratedReport::factory()->create([
            'document_id' => $document->id,
            'user_id' => $otherUser->id,
            'name' => 'Test Report',
        ]);

        Livewire::actingAs($user)
            ->test('reports.view-saved', ['documentId' => $document->id])
            ->call('deleteReport', $report->id)
            ->assertDispatched('showError', 'Report not found.');

        $this->assertDatabaseHas('generated_reports', ['id' => $report->id]);
    }

    public function test_shows_empty_state_when_no_reports(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test('reports.view-saved', ['documentId' => $document->id])
            ->assertSee('No reports generated yet for this document');
    }
}