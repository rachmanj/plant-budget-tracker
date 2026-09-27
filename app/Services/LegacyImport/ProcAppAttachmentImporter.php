<?php

namespace App\Services\LegacyImport;

use App\Models\DocumentAttachment;
use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseRequest;
use App\Models\User;
use App\Support\LegacyImport\ImportSummary;
use App\Support\LegacyImport\LegacyCsvReader;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcAppAttachmentImporter
{
    public const LEGACY_SOURCE = 'proc_app';

    public function __construct(
        private readonly LegacyCsvReader $csvReader,
    ) {}

    public function import(string $manifestPath, string $filesDirectory, bool $dryRun = false): ImportSummary
    {
        $summary = new ImportSummary('attachments_manifest');

        if (! is_readable($manifestPath)) {
            $summary->recordFailure(0, "manifest not readable: {$manifestPath}");

            return $summary;
        }

        $filesDirectory = rtrim($filesDirectory, '/');

        foreach ($this->csvReader->rows($manifestPath) as $line => $row) {
            $summary->read++;

            try {
                $docType = strtolower((string) ($this->stringValue($row, 'doc_type') ?? ''));
                $docNum = $this->stringValue($row, 'doc_num');
                $originalName = $this->stringValue($row, 'original_name', 'file_name');
                $size = $this->nullableInt($row, 'size');
                $mime = $this->stringValue($row, 'mime', 'mime_type');
                $storedRelative = $this->stringValue($row, 'stored_path', 'path');
                $legacyId = $this->nullableInt($row, 'legacy_id', 'id');
                $uploadedAt = $this->stringValue($row, 'uploaded_at');
                $uploadedByName = $this->stringValue($row, 'uploaded_by_name', 'uploaded_by');

                if (! in_array($docType, ['po', 'pr'], true) || $docNum === null || $originalName === null || $size === null) {
                    $summary->recordFailure($line, 'missing doc_type, doc_num, original_name, or size');

                    continue;
                }

                $register = $this->resolveRegister($docType, $docNum);
                if ($register === null) {
                    $summary->recordFailure($line, "no register match for {$docType} {$docNum}");

                    continue;
                }

                $attachableType = $docType === 'po' ? 'purchase_order' : 'purchase_request';

                $existing = DocumentAttachment::query()
                    ->where('attachable_type', $attachableType)
                    ->where('attachable_id', $register->id)
                    ->where('original_name', $originalName)
                    ->where('size', $size)
                    ->first();

                if ($existing !== null) {
                    $summary->skipped++;

                    continue;
                }

                $sourcePath = $storedRelative !== null ? $filesDirectory.'/'.$storedRelative : null;
                $fileMissing = $sourcePath === null || ! is_readable($sourcePath);

                $uploadedBy = $this->resolveUploaderId($uploadedByName);

                if ($fileMissing) {
                    if (! $dryRun) {
                        DocumentAttachment::query()->create([
                            'attachable_type' => $attachableType,
                            'attachable_id' => $register->id,
                            'original_name' => $originalName,
                            'stored_path' => 'legacy-import/unavailable/'.Str::uuid()->toString(),
                            'mime' => $mime,
                            'size' => $size,
                            'checksum' => null,
                            'uploaded_by' => $uploadedBy,
                            'legacy_source' => self::LEGACY_SOURCE,
                            'legacy_id' => $legacyId,
                            'file_unavailable' => true,
                        ]);
                    }
                    $summary->created++;

                    continue;
                }

                $actualSize = filesize($sourcePath);
                if ($actualSize === false || (int) $actualSize !== $size) {
                    $summary->recordFailure($line, 'file size mismatch');

                    continue;
                }

                $contents = file_get_contents($sourcePath);
                if ($contents === false) {
                    $summary->recordFailure($line, 'unable to read source file');

                    continue;
                }

                $checksum = hash('sha256', $contents);
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($originalName, PATHINFO_FILENAME)) ?: 'file';
                $safeName = $extension !== '' ? $safeBase.'.'.$extension : $safeBase;
                $storedPath = 'document-attachments/'.$attachableType.'/'.$register->id.'/'.Str::uuid()->toString().'-'.$safeName;

                if (! $dryRun) {
                    Storage::disk('local')->put($storedPath, $contents);

                    DocumentAttachment::query()->create([
                        'attachable_type' => $attachableType,
                        'attachable_id' => $register->id,
                        'original_name' => $originalName,
                        'stored_path' => $storedPath,
                        'mime' => $mime,
                        'size' => $size,
                        'checksum' => $checksum,
                        'uploaded_by' => $uploadedBy,
                        'legacy_source' => self::LEGACY_SOURCE,
                        'legacy_id' => $legacyId,
                        'file_unavailable' => false,
                    ]);
                }

                $summary->created++;
            } catch (\Throwable $e) {
                $summary->recordFailure($line, $e->getMessage());
            }
        }

        return $summary;
    }

    private function resolveRegister(string $docType, string $docNum): SapPurchaseOrder|SapPurchaseRequest|null
    {
        $numeric = ctype_digit($docNum) ? (int) $docNum : null;

        if ($docType === 'po') {
            return SapPurchaseOrder::query()
                ->where(function ($query) use ($docNum, $numeric) {
                    $query->where('legacy_doc_num', $docNum);
                    if ($numeric !== null) {
                        $query->orWhere('doc_num', $numeric);
                    }
                })
                ->first();
        }

        return SapPurchaseRequest::query()
            ->where(function ($query) use ($docNum, $numeric) {
                $query->where('legacy_doc_num', $docNum);
                if ($numeric !== null) {
                    $query->orWhere('doc_num', $numeric);
                }
            })
            ->first();
    }

    private function resolveUploaderId(?string $uploadedByName): ?int
    {
        if ($uploadedByName === null || $uploadedByName === '') {
            return null;
        }

        $user = User::query()->where('name', $uploadedByName)->first();

        return $user?->id;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  string  ...$keys
     */
    private function stringValue(array $row, ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  string  ...$keys
     */
    private function nullableInt(array $row, ...$keys): ?int
    {
        $value = $this->stringValue($row, ...$keys);

        return $value !== null && is_numeric($value) ? (int) $value : null;
    }
}
