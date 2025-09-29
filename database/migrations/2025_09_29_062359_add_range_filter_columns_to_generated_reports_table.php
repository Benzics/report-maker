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
            // Range filter columns for each of the three filters
            $table->string('filter_value_start')->nullable()->after('filter_value3');
            $table->string('filter_value_end')->nullable()->after('filter_value_start');
            $table->string('filter_value2_start')->nullable()->after('filter_value_end');
            $table->string('filter_value2_end')->nullable()->after('filter_value2_start');
            $table->string('filter_value3_start')->nullable()->after('filter_value2_end');
            $table->string('filter_value3_end')->nullable()->after('filter_value3_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generated_reports', function (Blueprint $table) {
            $table->dropColumn([
                'filter_value_start',
                'filter_value_end',
                'filter_value2_start',
                'filter_value2_end',
                'filter_value3_start',
                'filter_value3_end',
            ]);
        });
    }
};
