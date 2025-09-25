<?php

namespace Tests\Feature\Documents;

use App\Livewire\Documents\Manage;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_delete_document(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('test.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Create a document
        $document = Document::create([
            'user_id' => $user->id,
            'original_name' => 'test.xlsx',
            'path' => 'documents/' . $user->id . '/test.xlsx',
            'disk' => 'local',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => 20480,
            'status' => 'pending',
        ]);

        // Store a fake file
        Storage::disk('local')->put($document->path, 'fake content');

        $this->actingAs($user);

        Livewire::test(Manage::class)
            ->call('deleteDocument', $document->id)
            ->assertDispatched('showSuccess', __('Document deleted successfully.'));

        // Assert document is deleted from database
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);

        // Assert file is deleted from storage
        $this->assertFalse(Storage::disk('local')->exists($document->path));
    }

    public function test_cannot_delete_other_users_document(): void
    {
        Storage::fake('local');

        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var User $user2 */
        $user2 = User::factory()->create();

        // Create a document for user2
        $document = Document::create([
            'user_id' => $user2->id,
            'original_name' => 'test.xlsx',
            'path' => 'documents/' . $user2->id . '/test.xlsx',
            'disk' => 'local',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => 20480,
            'status' => 'pending',
        ]);

        $this->actingAs($user1);

        Livewire::test(Manage::class)
            ->call('deleteDocument', $document->id)
            ->assertDispatched('showError', __('Document not found.'));

        // Assert document still exists
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    public function test_handles_deletion_error_gracefully(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Create a document without storing the file
        $document = Document::create([
            'user_id' => $user->id,
            'original_name' => 'test.xlsx',
            'path' => 'documents/' . $user->id . '/test.xlsx',
            'disk' => 'local',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => 20480,
            'status' => 'pending',
        ]);

        $this->actingAs($user);

        Livewire::test(Manage::class)
            ->call('deleteDocument', $document->id)
            ->assertDispatched('showSuccess', __('Document deleted successfully.'));

        // Assert document is still deleted from database even if file doesn't exist
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }
}