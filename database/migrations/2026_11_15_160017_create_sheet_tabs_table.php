<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheet_tabs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('google_sheet_id')
                ->constrained('google_sheets')
                ->cascadeOnDelete();

            $table->string('tab_name');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['google_sheet_id', 'tab_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheet_tabs');
    }
};
