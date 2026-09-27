<?php

namespace App\Console\Commands;

use App\Services\LegacyImport\ProcAppLegacyImporter;
use App\Support\LegacyImport\ImportSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProcAppImportLegacy extends Command
{
    protected $signature = 'proc-app:import-legacy
                            {--directory= : Directory containing proc-app CSV exports (default: storage/app/legacy-import)}
                            {--dry-run : Parse files and print summary without writing to the database}';

    protected $description = 'Import proc-app legacy register rows, approvals, and item prices from CSV exports';

    public function handle(ProcAppLegacyImporter $importer): int
    {
        $directory = $this->option('directory')
            ?? Storage::disk('local')->path('legacy-import');

        $dryRun = (bool) $this->option('dry-run');

        if (! is_dir($directory)) {
            $this->error("Import directory does not exist: {$directory}");

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry run — no database changes will be made.');
        }

        $this->info("Reading legacy CSV files from: {$directory}");

        $summaries = $importer->import($directory, $dryRun);

        foreach ($summaries as $key => $summary) {
            $this->printSummary($key, $summary);
        }

        $this->newLine();
        $this->line('Item price rule: existing PMB rows are kept when their effective_date (or updated_at when source is not legacy_import) is newer than the legacy CSV effective_date.');

        $totalFailed = array_sum(array_map(static fn (ImportSummary $s) => $s->failed, $summaries));
        if ($totalFailed > 0) {
            $this->warn("Completed with {$totalFailed} row failure(s). See output above.");

            return self::FAILURE;
        }

        $this->info('Legacy import finished.');

        return self::SUCCESS;
    }

    private function printSummary(string $label, ImportSummary $summary): void
    {
        if ($summary->read === 0 && $summary->failed === 0) {
            return;
        }

        $this->line(sprintf(
            '%s: read=%d created=%d updated=%d skipped=%d failed=%d',
            $label,
            $summary->read,
            $summary->created,
            $summary->updated,
            $summary->skipped,
            $summary->failed,
        ));

        foreach ($summary->failures as $failure) {
            $this->line("  - {$failure}");
        }
    }
}
