<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SheetTab extends Model
{
    protected $fillable = [
        'google_sheet_id',
        'tab_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function googleSheet(): BelongsTo
    {
        return $this->belongsTo(GoogleSheet::class);
    }

    public function columnMappings(): HasMany
    {
        return $this->hasMany(ColumnMapping::class);
    }

    public function importLogs(): HasMany
    {
        return $this->hasMany(ImportLog::class);
    }
}
