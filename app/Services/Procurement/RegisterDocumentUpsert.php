<?php

namespace App\Services\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class RegisterDocumentUpsert
{
    /**
     * @param  class-string<Model>  $documentModelClass
     * @param  class-string<Model>  $lineModelClass
     * @param  array<string, mixed>  $headerAttributes
     * @param  array<string, int>  $summary
     * @return array{document: Model, created: bool, updated: bool, line_sap_doc_entry: int|string}
     */
    public function upsert(
        string $documentModelClass,
        string $lineModelClass,
        string $documentForeignKey,
        int|string $sapDocEntry,
        mixed $docNum,
        array $headerAttributes,
        array &$summary,
    ): array {
        /** @var Model|null $document */
        $document = null;

        if ($this->docNumPresent($docNum)) {
            $document = $documentModelClass::query()->where('doc_num', $docNum)->first();
            if ($document !== null) {
                $summary['documents_matched_by_doc_num']++;
            }
        }

        if ($document === null) {
            $document = $documentModelClass::query()->where('sap_doc_entry', $sapDocEntry)->first();
        }

        if ($document === null) {
            try {
                $document = $documentModelClass::query()->create([
                    'sap_doc_entry' => $sapDocEntry,
                    ...$headerAttributes,
                ]);

                return [
                    'document' => $document,
                    'created' => true,
                    'updated' => false,
                    'line_sap_doc_entry' => $sapDocEntry,
                ];
            } catch (QueryException $exception) {
                if (! $this->isSapDocEntryDuplicate($exception)) {
                    throw $exception;
                }

                $summary['duplicate_retries']++;
                $document = $documentModelClass::query()->where('sap_doc_entry', $sapDocEntry)->firstOrFail();
            }
        }

        $lineSapDocEntry = $this->reconcileSapDocEntry(
            $document,
            $documentModelClass,
            $lineModelClass,
            $documentForeignKey,
            $sapDocEntry,
            $summary,
        );

        $document->fill($headerAttributes);

        $updated = false;
        if ($document->isDirty()) {
            $document->save();
            $updated = true;
        }

        return [
            'document' => $document,
            'created' => false,
            'updated' => $updated,
            'line_sap_doc_entry' => $lineSapDocEntry,
        ];
    }

    /**
     * @param  class-string<Model>  $documentModelClass
     * @param  class-string<Model>  $lineModelClass
     * @param  array<string, int>  $summary
     */
    private function reconcileSapDocEntry(
        Model $document,
        string $documentModelClass,
        string $lineModelClass,
        string $documentForeignKey,
        int|string $sapDocEntry,
        array &$summary,
    ): int|string {
        $currentEntry = $document->getAttribute('sap_doc_entry');

        if ((string) $currentEntry === (string) $sapDocEntry) {
            return $currentEntry;
        }

        $targetTaken = $documentModelClass::query()
            ->where('sap_doc_entry', $sapDocEntry)
            ->whereKeyNot($document->getKey())
            ->exists();

        if ($targetTaken) {
            $summary['entry_conflicts_skipped']++;

            return $currentEntry;
        }

        $lineModelClass::query()
            ->where($documentForeignKey, $document->getKey())
            ->where('sap_doc_entry', $currentEntry)
            ->update(['sap_doc_entry' => $sapDocEntry]);

        $document->setAttribute('sap_doc_entry', $sapDocEntry);

        return $sapDocEntry;
    }

    private function docNumPresent(mixed $docNum): bool
    {
        if ($docNum === null) {
            return false;
        }

        if (is_string($docNum) && trim($docNum) === '') {
            return false;
        }

        return true;
    }

    private function isSapDocEntryDuplicate(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? '';
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        if ($sqlState !== '23000' || $driverCode !== 1062) {
            return false;
        }

        $message = $exception->getMessage();

        return str_contains($message, 'sap_doc_entry')
            || str_contains($message, 'sap_purchase_requests_sap_doc_entry_unique')
            || str_contains($message, 'sap_purchase_orders_sap_doc_entry_unique');
    }
}