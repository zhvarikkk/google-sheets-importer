<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColumnMapping extends Model
{
    protected $fillable = [
        'sheet_tab_id',
        'source_column',
        'destination_table',
        'destination_column',
        'data_type',
        'is_nullable',
        'is_required',
        'default_value',
    ];

    protected function casts(): array
    {
        return [
            'is_nullable' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    public function sheetTab(): BelongsTo
    {
        return $this->belongsTo(SheetTab::class);
    }
}
