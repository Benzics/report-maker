<?php

namespace Tests\Feature\Documents;

use App\Livewire\Documents\Upload;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploads_valid_excel_and_creates_document(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('test.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($user);

        Livewire::test(Upload::class)
            ->set('file', $file)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(session()->has('livewire-toast'));
        $toastData = session('livewire-toast');
        $this->assertEquals('success', $toastData['type']);
        $this->assertEquals(__('Document uploaded successfully.'), $toastData['message']);

        $document = Document::where('user_id', $user->id)->first();
        $this->assertNotNull($document);
        $this->assertSame('test.xlsx', $document->original_name);
        $this->assertTrue(Storage::disk('local')->exists($document->path));
    }

    public function test_requires_a_file(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Upload::class)
            ->call('save')
            ->assertHasErrors(['file' => 'required']);
    }

    public function test_rejects_unsupported_file_types(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('notes.txt', 5, 'text/plain');

        $this->actingAs($user);

        Livewire::test(Upload::class)
            ->set('file', $file)
            ->call('save')
            ->assertHasErrors(['file' => 'mimes']);
    }
}


