<?php

namespace App\Services;

use App\Models\SheetTab;
use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ColumnMappingEngine
{
    /**
     * Convert spreadsheet rows into destination-table records.
     *
     * A source column can map to multiple destination columns,
     * including columns in different destination tables.
     */
    public function transform(SheetTab $sheetTab, array $sheetData): array
    {
        $headers = $sheetData['headers'] ?? [];
        $rows = $sheetData['rows'] ?? [];

        $mappings = $sheetTab->columnMappings()->get();

        if ($mappings->isEmpty()) {
            throw new RuntimeException('No column mappings configured for this sheet tab.');
        }

        // Validate the mapping configuration before processing rows.
        foreach ($mappings as $mapping) {
            $this->validateIdentifier($mapping->destination_table);
            $this->validateIdentifier($mapping->destination_column);

            if (!in_array($mapping->data_type, [
                'string',
                'integer',
                'decimal',
                'date',
                'boolean',
            ], true)) {
                throw new RuntimeException(
                    "Unsupported data type: {$mapping->data_type}"
                );
            }

            if (!in_array($mapping->source_column, $headers, true)) {
                throw new RuntimeException(
                    "Source column '{$mapping->source_column}' was not found in the spreadsheet."
                );
            }
        }

        $records = [];
        $errors = [];

        foreach ($rows as $rowIndex => $sourceRow) {
            // Build this source row separately so that an invalid value
            // cannot cause a partial import into different tables.
            $rowRecords = [];
            $rowErrors = [];

            foreach ($mappings as $mapping) {
                $value = $sourceRow[$mapping->source_column] ?? null;

                try {
                    $value = $this->resolveValue($value, $mapping);

                    $rowRecords[$mapping->destination_table]
                        [$mapping->destination_column] = $value;

                } catch (Throwable $exception) {
                    $rowErrors[] = [
                        'source_row' => $rowIndex + 2,
                        'source_column' => $mapping->source_column,
                        'destination_table' => $mapping->destination_table,
                        'destination_column' => $mapping->destination_column,
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            if (!empty($rowErrors)) {
                $errors = array_merge($errors, $rowErrors);
                continue;
            }

            foreach ($rowRecords as $table => $record) {
                $records[$table][] = $record;
            }
        }

        return [
            'records' => $records,
            'rows_read' => count($rows),
            'rows_valid' => count($rows) - count(array_unique(
                array_column($errors, 'source_row')
            )),
            'rows_failed' => count(array_unique(
                array_column($errors, 'source_row')
            )),
            'errors' => $errors,
        ];
    }

    /**
     * Apply defaults, nullability and required-field rules,
     * then convert the value to its configured type.
     */
    private function resolveValue(mixed $value, object $mapping): mixed
    {
        if ($value === '') {
            $value = null;
        }

        if ($value === null && $mapping->default_value !== null) {
            $value = $mapping->default_value;
        }

        if ($value === null) {
            if ($mapping->is_required || !$mapping->is_nullable) {
                throw new InvalidArgumentException('A value is required.');
            }

            return null;
        }

        return $this->convertValue($value, $mapping->data_type);
    }

    private function convertValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'string' => (string) $value,

            'integer' => $this->toInteger($value),

            'decimal' => $this->toDecimal($value),

            'boolean' => $this->toBoolean($value),

            'date' => $this->toDate($value),

            default => throw new InvalidArgumentException(
                "Unsupported data type: {$type}"
            ),
        };
    }

    private function toInteger(mixed $value): int
    {
        if (!is_numeric($value) || (float) $value != (int) $value) {
            throw new InvalidArgumentException(
                "Value '{$value}' is not a valid integer."
            );
        }

        return (int) $value;
    }

    private function toDecimal(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "Value '{$value}' is not a valid decimal."
            );
        }

        // Keep decimal precision rather than converting to a float.
        return (string) $value;
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return match (strtolower(trim((string) $value))) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default => throw new InvalidArgumentException(
                "Value '{$value}' is not a valid boolean."
            ),
        };
    }

    private function toDate(mixed $value): string
    {
        // Google Sheets dates returned with UNFORMATTED_VALUE
        // are numeric serial values, based on 1899-12-30.
        if (is_numeric($value)) {
            $days = (int) $value;
            $fraction = (float) $value - $days;
            $seconds = (int) round($fraction * 86400);

            return Carbon::create(1899, 12, 30)
                ->addDays($days)
                ->addSeconds($seconds)
                ->toDateString();
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            throw new InvalidArgumentException(
                "Value '{$value}' is not a valid date."
            );
        }
    }

    /**
     * Only allow simple SQL identifiers.
     * This is a basic safeguard, not a substitute for an allowlist
     * when we start creating or writing database tables.
     */
    private function validateIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new RuntimeException(
                "Invalid database identifier: {$identifier}"
            );
        }
    }
}