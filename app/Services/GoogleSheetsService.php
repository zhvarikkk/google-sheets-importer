<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use RuntimeException;

class GoogleSheetsService
{
    private Sheets $sheets;

    public function __construct()
    {
        $credentialsPath = base_path(
            config('google-sheets.credentials')
        );

        if (!is_file($credentialsPath)) {
            throw new RuntimeException(
                'Google Service Account credentials not found.'
            );
        }

        $client = new Client();

        $client->setApplicationName(config('app.name'));

        $client->setAuthConfig($credentialsPath);

        $client->setScopes([
            Sheets::SPREADSHEETS_READONLY,
        ]);

        $this->sheets = new Sheets($client);
    }

    public function getSpreadsheetInfo(string $spreadsheetId): array
{
    $spreadsheet = $this->sheets->spreadsheets->get(
        $spreadsheetId,
        [
            'fields' => 'spreadsheetId,properties(title),sheets(properties(sheetId,title,index))',
        ]
    );

    $tabs = [];

    foreach ($spreadsheet->getSheets() as $sheet) {
        $properties = $sheet->getProperties();

        $tabs[] = [
            'id' => $properties->getSheetId(),
            'title' => $properties->getTitle(),
            'index' => $properties->getIndex(),
        ];
    }

    return [
        'spreadsheet_id' => $spreadsheet->getSpreadsheetId(),
        'title' => $spreadsheet->getProperties()->getTitle(),
        'tabs' => $tabs,
    ];
}

public function readTab(
    string $spreadsheetId,
    string $tabName
): array {
    // Escape single quotes in the tab name.
    $escapedTabName = str_replace("'", "''", $tabName);

    // Request all columns from the specified tab.
    $range = "'{$escapedTabName}'!A:ZZ";

    $response = $this->sheets->spreadsheets_values->get(
        $spreadsheetId,
        $range,
        [
            'majorDimension' => 'ROWS',
            'valueRenderOption' => 'UNFORMATTED_VALUE',
        ]
    );

    $values = $response->getValues() ?? [];

    if (empty($values)) {
        return [
            'headers' => [],
            'rows' => [],
        ];
    }

    // First row contains column names.
    $headers = array_map(
        fn ($header) => trim((string) $header),
        $values[0]
    );

    if (in_array('', $headers, true)) {
        throw new RuntimeException(
            'Spreadsheet contains an empty column header.'
        );
    }

    if (count($headers) !== count(array_unique($headers))) {
        throw new RuntimeException(
            'Spreadsheet contains duplicate column headers.'
        );
    }

    // Remaining rows contain actual data.
    $dataRows = array_slice($values, 1);

    $rows = [];

    foreach ($dataRows as $row) {
        // Google omits trailing empty cells.
        $row = array_slice(
            array_pad($row, count($headers), null),
            0,
            count($headers)
        );

        $rows[] = array_combine($headers, $row);
    }

    return [
        'headers' => $headers,
        'rows' => $rows,
    ];
}
}