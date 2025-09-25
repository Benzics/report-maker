<?php

namespace Tests\Feature\Documents;

use App\Livewire\Documents\Manage;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_search_documents_by_name(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Create test documents
        Document::create([
            'user_id' => $user->id,
            'original_name' => 'report-2024.xlsx',
            'path' => 'documents/1/report-2024.xlsx',
            'disk' => 'local',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => 20480,
            'status' => 'pending',
        ]);

        Document::create([
            'user_id' => $user->id,
            'original_name' => 'budget-analysis.xlsx',
            'path' => 'documents/1/budget-analysis.xlsx',
            'disk' => 'local',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => 15360,
            'status' => 'pending',
        ]);

        Document::create([
            'user_id' => $user->id,
            'original_name' => 'sales-data.csv',
            'path' => 'documents/1/sales-data.csv',
            'disk' => 'local',
            'mime_type' => 'text/csv',
            'size' => 10240,
            'status' => 'pending',
        ]);

        $this->actingAs($user);

        // Test searching for "report"
        Livewire::test(Manage::class)
            ->set('search', 'report')
            ->assertSee('report-2024.xlsx')
            ->assertDontSee('budget-analysis.xlsx')
            ->assertDontSee('sales-data.csv');

        // Test searching for "budget"
        Livewire::test(Manage::class)
            ->set('search', 'budget')
            ->assertSee('budget-analysis.xlsx')
            ->assertDontSee('report-2024.xlsx')
            ->assertDontSee('sales-data.csv');

        // Test searching for "data"
        Livewire::test(Manage::class)
            ->set('search', 'data')
            ->assertSee('sales-data.csv')
            ->assertDontSee('report-2024.xlsx')
            ->assertDontSee('budget-analysis.xlsx');
    }

    public function test_search_returns_no_results_for_non_matching_query(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Document::create([
            'user_id' => $user->id,
            'original_name' => 'report-2024.xlsx',
            'path' => 'documents/1/report-2024.xlsx',
            'disk' => 'local',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => 20480,
            'status' => 'pending',
        ]);

        $this->actingAs($user);

        Livewire::test(Manage::class)
            ->set('search', 'nonexistent')
            ->assertSee('No documents uploaded yet.');
    }

    public function test_search_is_case_insensitive(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Document::create([
            'user_id' => $user->id,
            'original_name' => 'Report-2024.xlsx',
            'path' => 'documents/1/Report-2024.xlsx',
            'disk' => 'local',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => 20480,
            'status' => 'pending',
        ]);

        $this->actingAs($user);

        Livewire::test(Manage::class)
            ->set('search', 'report')
            ->assertSee('Report-2024.xlsx');
    }

    public function test_clearing_search_shows_all_documents(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Document::create([
            'user_id' => $user->id,
            'original_name' => 'report-2024.xlsx',
            'path' => 'documents/1/report-2024.xlsx',
            'disk' => 'local',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => 20480,
            'status' => 'pending',
        ]);

        Document::create([
            'user_id' => $user->id,
            'original_name' => 'budget-analysis.xlsx',
            'path' => 'documents/1/budget-analysis.xlsx',
            'disk' => 'local',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => 15360,
            'status' => 'pending',
        ]);

        $this->actingAs($user);

        Livewire::test(Manage::class)
            ->set('search', 'report')
            ->assertSee('report-2024.xlsx')
            ->assertDontSee('budget-analysis.xlsx')
            ->set('search', '')
            ->assertSee('report-2024.xlsx')
            ->assertSee('budget-analysis.xlsx');
    }
}