<?php

namespace App\Services;

use App\Models\SheetTab;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DataImportService
{
    public function __construct(
        private DestinationSchemaManager $schemaManager
    ) {
    }

    /**
     * Ensure destination schemas exist and insert transformed records.
     */
    public function import(SheetTab $sheetTab, array $records): array
    {
        // Create tables and columns before inserting data.
        // Database DDL may implicitly commit, so this is intentionally
        // outside the data insertion transaction.
        $this->schemaManager->ensureSchema($sheetTab);

        // Only permit tables and columns declared in this tab's mappings.
        $mappings = $sheetTab->columnMappings()->get();

        $allowed = [];

        foreach ($mappings as $mapping) {
            $allowed[$mapping->destination_table][] =
                $mapping->destination_column;
        }

        foreach ($records as $tableName => $tableRecords) {
            if (!array_key_exists($tableName, $allowed)) {
                throw new RuntimeException(
                    "Unconfigured destination table: {$tableName}"
                );
            }

            foreach ($tableRecords as $record) {
                $unexpectedColumns = array_diff(
                    array_keys($record),
                    $allowed[$tableName]
                );

                if (!empty($unexpectedColumns)) {
                    throw new RuntimeException(
                        "Unexpected destination columns for {$tableName}: " .
                        implode(', ', $unexpectedColumns)
                    );
                }
            }
        }

        $insertedCounts = [];

        // All inserts succeed together, or they are rolled back together.
        DB::transaction(function () use ($records, &$insertedCounts) {
            foreach ($records as $tableName => $tableRecords) {
                if (empty($tableRecords)) {
                    $insertedCounts[$tableName] = 0;
                    continue;
                }

                $count = 0;

                // Insert in batches instead of sending one huge query.
                foreach (array_chunk($tableRecords, 500) as $chunk) {
                    DB::table($tableName)->insert($chunk);
                    $count += count($chunk);
                }

                $insertedCounts[$tableName] = $count;
            }
        });

        return [
            'success' => true,
            'inserted' => $insertedCounts,
            'total_inserted' => array_sum($insertedCounts),
        ];
    }
}