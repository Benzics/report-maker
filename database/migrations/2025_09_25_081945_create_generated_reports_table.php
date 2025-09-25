<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('selected_columns'); // Array of column indices
            $table->string('filter_column')->nullable(); // Column index for filtering
            $table->string('filter_value')->nullable(); // Value to filter by
            $table->string('file_path'); // Path to generated report file
            $table->string('file_name'); // Original filename for download
            $table->string('file_size'); // File size in bytes
            $table->string('mime_type'); // MIME type of the file
            $table->boolean('is_saved')->default(false); // Whether user chose to save to database
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['document_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
