<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('google_sheet_id')
                ->nullable()
                ->constrained('google_sheets')
                ->nullOnDelete();

            $table->foreignId('sheet_tab_id')
                ->nullable()
                ->constrained('sheet_tabs')
                ->nullOnDelete();

            // Snapshot of the source
            $table->string('spreadsheet_name');
            $table->string('spreadsheet_id');

            // pending, processing, success, failed
            $table->string('status')->default('pending');

            $table->unsignedInteger('rows_read')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);

            $table->longText('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
