<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    protected $fillable = [
        'google_sheet_id',
        'sheet_tab_id',
        'spreadsheet_name',
        'spreadsheet_id',
        'status',
        'rows_read',
        'rows_imported',
        'rows_failed',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function googleSheet(): BelongsTo
    {
        return $this->belongsTo(GoogleSheet::class);
    }

    public function sheetTab(): BelongsTo
    {
        return $this->belongsTo(SheetTab::class);
    }
}
