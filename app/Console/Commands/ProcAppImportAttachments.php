<?php

namespace App\Console\Commands;

use App\Services\LegacyImport\ProcAppAttachmentImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProcAppImportAttachments extends Command
{
    protected $signature = 'proc-app:import-attachments
                            {--manifest= : Path to attachment manifest CSV (default: storage/app/legacy-import/attachments_manifest.csv)}
                            {--files-directory= : Directory containing attachment files referenced by stored_path}
                            {--dry-run : Validate manifest and print summary without copying files or writing rows}';

    protected $description = 'Import proc-app PO/PR attachments from a manifest CSV into PMB private storage';

    public function handle(ProcAppAttachmentImporter $importer): int
    {
        $manifest = $this->option('manifest')
            ?? Storage::disk('local')->path('legacy-import/attachments_manifest.csv');

        $filesDirectory = $this->option('files-directory')
            ?? Storage::disk('local')->path('legacy-import/files');

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no files or database rows will be written.');
        }

        $this->info("Manifest: {$manifest}");
        $this->info("Files directory: {$filesDirectory}");

        $summary = $importer->import($manifest, $filesDirectory, $dryRun);

        $this->line(sprintf(
            'attachments: read=%d created=%d updated=%d skipped=%d failed=%d',
            $summary->read,
            $summary->created,
            $summary->updated,
            $summary->skipped,
            $summary->failed,
        ));

        foreach ($summary->failures as $failure) {
            $this->line("  - {$failure}");
        }

        if ($summary->failed > 0) {
            return self::FAILURE;
        }

        $this->info('Attachment import finished.');

        return self::SUCCESS;
    }
}
