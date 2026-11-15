<?php

namespace Database\Seeders;

use App\Models\GoogleSheet;
use Illuminate\Database\Seeder;

class GoogleSheetsSeeder extends Seeder
{
    public function run(): void
    {
        $sheet = GoogleSheet::updateOrCreate(
            ['spreadsheet_id' => 'example_spreadsheet_123'],
            [
                'name' => 'Sales Report',
                'is_active' => true,
            ]
        );

        $tab = $sheet->tabs()->updateOrCreate(
            ['tab_name' => 'Sales'],
            ['is_active' => true]
        );

        $mappings = [
            [
                'source_column' => 'date',
                'destination_table' => 'sales',
                'destination_column' => 'date',
                'data_type' => 'date',
                'is_nullable' => false,
                'is_required' => true,
            ],
            [
                'source_column' => 'product_name',
                'destination_table' => 'sales',
                'destination_column' => 'name',
                'data_type' => 'string',
                'is_nullable' => false,
                'is_required' => true,
            ],
            [
                'source_column' => 'price',
                'destination_table' => 'sales',
                'destination_column' => 'product_price',
                'data_type' => 'decimal',
                'is_nullable' => true,
                'is_required' => false,
            ],
            [
                'source_column' => 'amount',
                'destination_table' => 'sales',
                'destination_column' => 'amount',
                'data_type' => 'integer',
                'is_nullable' => true,
                'is_required' => false,
            ],
            [
                'source_column' => 'amount',
                'destination_table' => 'financial_reports',
                'destination_column' => 'total_amount',
                'data_type' => 'integer',
                'is_nullable' => true,
                'is_required' => false,
            ],
        ];

        foreach ($mappings as $mapping) {
            $tab->columnMappings()->updateOrCreate(
                [
                    'source_column' => $mapping['source_column'],
                    'destination_table' => $mapping['destination_table'],
                    'destination_column' => $mapping['destination_column'],
                ],
                $mapping
            );
        }
    }
}
