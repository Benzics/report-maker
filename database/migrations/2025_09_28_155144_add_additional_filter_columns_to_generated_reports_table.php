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
        Schema::table('generated_reports', function (Blueprint $table) {
            $table->string('filter_column2')->nullable()->after('filter_value');
            $table->string('filter_value2')->nullable()->after('filter_column2');
            $table->string('filter_column3')->nullable()->after('filter_value2');
            $table->string('filter_value3')->nullable()->after('filter_column3');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generated_reports', function (Blueprint $table) {
            $table->dropColumn(['filter_column2', 'filter_value2', 'filter_column3', 'filter_value3']);
        });
    }
};
