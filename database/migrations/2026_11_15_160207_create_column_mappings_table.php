<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('column_mappings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sheet_tab_id')
                ->constrained('sheet_tabs')
                ->cascadeOnDelete();

            // Google Sheets source column
            $table->string('source_column');

            // MySQL destination
            $table->string('destination_table');
            $table->string('destination_column');

            // Database column configuration
            $table->string('data_type')->default('string');
            $table->boolean('is_nullable')->default(true);
            $table->boolean('is_required')->default(false);

            $table->text('default_value')->nullable();

            $table->timestamps();

            $table->unique([
                'sheet_tab_id',
                'source_column',
                'destination_table',
                'destination_column'
            ], 'column_mappings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('column_mappings');
    }
};
