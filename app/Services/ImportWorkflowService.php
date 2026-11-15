<?php

namespace App\Services;

use App\Models\ImportLog;
use App\Models\SheetTab;
use RuntimeException;
use Throwable;

class ImportWorkflowService
{
    public function __construct(
        private GoogleSheetsService $googleSheetsService,
        private ColumnMappingEngine $mappingEngine,
        private DataImportService $dataImportService
    ) {
    }

    public function importTab(SheetTab $sheetTab): array
    {
        $sheetTab->loadMissing('googleSheet');

        $googleSheet = $sheetTab->googleSheet;

        if (!$googleSheet) {
            throw new RuntimeException(
                'The spreadsheet configuration was not found.'
            );
        }

        $startedAt = now();

        // Create a log before starting the import.
        $log = ImportLog::create([
            'google_sheet_id' => $googleSheet->id,
            'sheet_tab_id' => $sheetTab->id,
            'spreadsheet_name' => $googleSheet->name,
            'spreadsheet_id' => $googleSheet->spreadsheet_id,
            'status' => 'running',
            'rows_read' => 0,
            'rows_imported' => 0,
            'rows_failed' => 0,
            'started_at' => $startedAt,
        ]);

        $rowsRead = 0;
        $rowsImported = 0;
        $rowsFailed = 0;

        try {
            // 1. Read data from Google Sheets.
            $sheetData = $this->googleSheetsService->readTab(
                $googleSheet->spreadsheet_id,
                $sheetTab->tab_name
            );

            // 2. Transform and validate source data.
            $transformed = $this->mappingEngine->transform(
                $sheetTab,
                $sheetData
            );

            $rowsRead = $transformed['rows_read'] ?? 0;
            $rowsFailed = $transformed['rows_failed'] ?? 0;
            $errors = $transformed['errors'] ?? [];

            // 3. Create destination schema and insert valid records.
            $importResult = $this->dataImportService->import(
                $sheetTab,
                $transformed['records'] ?? []
            );

            $rowsImported = $importResult['total_inserted'] ?? 0;

            // 4. Determine the final import status.
            if (empty($errors)) {
                $status = 'success';
            } elseif ($rowsImported > 0) {
                $status = 'partial';
            } else {
                $status = 'failed';
            }

            // Keep a manageable amount of validation detail in the log.
            $errorMessage = empty($errors)
                ? null
                : json_encode(
                    array_slice($errors, 0, 20),
                    JSON_UNESCAPED_UNICODE
                );

            // 5. Finish the import log.
            $log->update([
                'status' => $status,
                'rows_read' => $rowsRead,
                'rows_imported' => $rowsImported,
                'rows_failed' => $rowsFailed,
                'error_message' => $errorMessage,
                'finished_at' => now(),
            ]);

            return [
                'success' => $status === 'success',
                'status' => $status,
                'log_id' => $log->id,
                'rows_read' => $rowsRead,
                'rows_imported' => $rowsImported,
                'rows_failed' => $rowsFailed,
                'errors' => $errors,
                'inserted' => $importResult['inserted'] ?? [],
            ];

        } catch (Throwable $exception) {

            // Record the failure instead of losing the import history.
            $log->update([
                'status' => 'failed',
                'rows_read' => $rowsRead,
                'rows_imported' => $rowsImported,
                'rows_failed' => $rowsFailed,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'log_id' => $log->id,
                'rows_read' => $rowsRead,
                'rows_imported' => $rowsImported,
                'rows_failed' => $rowsFailed,
                'errors' => [$exception->getMessage()],
            ];
        }
    }
}