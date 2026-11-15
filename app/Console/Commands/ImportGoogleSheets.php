<?php

namespace App\Console\Commands;

use App\Jobs\ImportGoogleSheetTab;
use App\Models\SheetTab;
use Illuminate\Console\Command;

class ImportGoogleSheets extends Command
{
    protected $signature = 'sheets:import';

    protected $description = 'Queue imports for all active Google Sheets tabs';

    public function handle(): int
    {
        $tabs = SheetTab::query()
            ->where('is_active', true)
            ->whereHas('googleSheet', function ($query) {
                $query->where('is_active', true);
            })
            ->get();

        if ($tabs->isEmpty()) {
            $this->warn('No active Google Sheets tabs found.');

            return self::SUCCESS;
        }

        foreach ($tabs as $tab) {
            ImportGoogleSheetTab::dispatch($tab);

            $this->info(
                "Queued: {$tab->tab_name} (Tab ID: {$tab->id})"
            );
        }

        $this->newLine();

        $this->info(
            "Successfully queued {$tabs->count()} import job(s)."
        );

        return self::SUCCESS;
    }
}
