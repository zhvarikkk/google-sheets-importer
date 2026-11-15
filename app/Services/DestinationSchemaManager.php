<?php

namespace App\Services;

use App\Models\SheetTab;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DestinationSchemaManager
{
    /**
     * Create destination tables and columns when they do not exist.
     */
    public function ensureSchema(SheetTab $sheetTab): void
    {
        $mappings = $sheetTab->columnMappings()->get();

        if ($mappings->isEmpty()) {
            throw new RuntimeException('No destination mappings configured.');
        }

        $tables = $mappings->groupBy('destination_table');

        foreach ($tables as $tableName => $tableMappings) {
            $this->validateTableName($tableName);

            // Validate every configured destination column.
            foreach ($tableMappings as $mapping) {
                $this->validateColumnName($mapping->destination_column);
            }

            // A single destination column cannot have conflicting types.
            foreach ($tableMappings->groupBy('destination_column') as $column => $columnMappings) {
                $types = $columnMappings->pluck('data_type')->unique();

                if ($types->count() > 1) {
                    throw new RuntimeException(
                        "Conflicting data types configured for {$tableName}.{$column}."
                    );
                }
            }

            if (!Schema::hasTable($tableName)) {
                $this->createTable($tableName, $tableMappings);
            } else {
                $this->addMissingColumns($tableName, $tableMappings);
            }
        }
    }

    private function createTable(string $tableName, $mappings): void
    {
        Schema::create($tableName, function (Blueprint $table) use ($mappings) {
            $table->id();
            $table->timestamps();

            foreach ($mappings as $mapping) {
                $this->addMappedColumn($table, $mapping, false);
            }
        });
    }

    private function addMissingColumns(string $tableName, $mappings): void
    {
        // Adding a NOT NULL column to a populated table can fail.
        // Check whether this table already contains records.
        $hasRows = DB::table($tableName)->exists();

        foreach ($mappings as $mapping) {
            $columnName = $mapping->destination_column;

            if (Schema::hasColumn($tableName, $columnName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($mapping, $hasRows) {
                // For an existing populated table, add the new column
                // as nullable. The import engine still enforces the
                // configured required/nullability rules.
                $this->addMappedColumn($table, $mapping, $hasRows);
            });
        }
    }

    private function addMappedColumn(
        Blueprint $table,
        object $mapping,
        bool $forceNullable
    ): void {
        $columnName = $mapping->destination_column;

        $column = match ($mapping->data_type) {
            'string' => $table->string($columnName, 255),

            'integer' => $table->bigInteger($columnName),

            'decimal' => $table->decimal($columnName, 18, 4),

            'date' => $table->date($columnName),

            'boolean' => $table->boolean($columnName),

            default => throw new RuntimeException(
                "Unsupported data type: {$mapping->data_type}"
            ),
        };

        if ($forceNullable || $mapping->is_nullable) {
            $column->nullable();
        }

        if ($mapping->default_value !== null) {
            $column->default($mapping->default_value);
        }
    }

    private function validateTableName(string $name): void
    {
        $this->validateIdentifier($name);

        $reservedTables = [
            'migrations',
            'users',
            'password_reset_tokens',
            'sessions',
            'jobs',
            'failed_jobs',
            'personal_access_tokens',
            'column_mappings',
            'sheet_tabs',
            'google_sheets',
            'import_logs',
        ];

        if (in_array(strtolower($name), $reservedTables, true)) {
            throw new RuntimeException(
                "The destination table '{$name}' is reserved."
            );
        }
    }

    private function validateColumnName(string $name): void
    {
        $this->validateIdentifier($name);

        $reservedColumns = [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        if (in_array(strtolower($name), $reservedColumns, true)) {
            throw new RuntimeException(
                "The destination column '{$name}' is reserved."
            );
        }
    }

    private function validateIdentifier(string $name): void
    {
        if (
            strlen($name) > 64 ||
            !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)
        ) {
            throw new RuntimeException(
                "Invalid database identifier: {$name}"
            );
        }
    }
}