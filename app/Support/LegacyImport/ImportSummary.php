<?php

namespace App\Support\LegacyImport;

class ImportSummary
{
    /** @var array<int, string> */
    public array $failures = [];

    public function __construct(
        public string $label = '',
        public int $read = 0,
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public int $failed = 0,
    ) {}

    public function recordFailure(int $line, string $message): void
    {
        $this->failed++;
        $this->failures[] = "{$this->label} line {$line}: {$message}";
    }

    public function merge(self $other): void
    {
        $this->read += $other->read;
        $this->created += $other->created;
        $this->updated += $other->updated;
        $this->skipped += $other->skipped;
        $this->failed += $other->failed;
        $this->failures = array_merge($this->failures, $other->failures);
    }
}
