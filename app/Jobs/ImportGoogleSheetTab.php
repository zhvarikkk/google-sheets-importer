<?php

namespace App\Jobs;

use App\Models\SheetTab;
use App\Services\ImportWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class ImportGoogleSheetTab implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [60, 300];

    public function __construct(
        public SheetTab $sheetTab
    ) {
    }

    public function handle(ImportWorkflowService $workflow): void
    {
        $result = $workflow->importTab($this->sheetTab);

        // The workflow records the failed attempt in import_logs.
        // Throwing lets Laravel retry the job.
        if ($result['status'] === 'failed') {
            throw new RuntimeException(
                'Google Sheets import failed. Log ID: '
                . ($result['log_id'] ?? 'unknown')
            );
        }
    }
}
