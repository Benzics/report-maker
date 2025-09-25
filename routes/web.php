<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Http\Controllers\DashboardController;
use App\Livewire\Documents\Upload as UploadDocument;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('dashboard', App\Livewire\Documents\Manage::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('documents/upload', UploadDocument::class)
    ->middleware(['auth', 'verified'])
    ->name('documents.upload');

Route::get('reports/generate/{documentId}', App\Livewire\Reports\Generate::class)
    ->middleware(['auth', 'verified'])
    ->name('reports.generate');

Route::get('reports/preview', App\Livewire\Reports\Preview::class)
    ->middleware(['auth', 'verified'])
    ->name('reports.preview');

Route::get('reports/saved/{documentId}', App\Livewire\Reports\ViewSaved::class)
    ->middleware(['auth', 'verified'])
    ->name('reports.saved');

Route::get('reports/download/{reportId}', [App\Http\Controllers\Reports\DownloadController::class, 'download'])
    ->middleware(['auth', 'verified'])
    ->name('reports.download');

// API route for checking report completion status
Route::get('api/reports/check-completion', [App\Http\Controllers\Api\ReportStatusController::class, 'checkCompletion'])
    ->middleware(['auth', 'verified']);

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
});

require __DIR__.'/auth.php';
