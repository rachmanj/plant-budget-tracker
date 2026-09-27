<?php

namespace App\Support\LegacyImport;

use InvalidArgumentException;

class LegacyCsvReader
{
    /**
     * @return \Generator<int, array<string, string|null>>
     */
    public function rows(string $path): \Generator
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException("CSV file not readable: {$path}");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open CSV: {$path}");
        }

        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headerLine = fgets($handle);
        if ($headerLine === false || trim($headerLine) === '') {
            fclose($handle);

            return;
        }

        $delimiter = str_contains($headerLine, ';') ? ';' : ',';
        $headers = array_map(
            static fn (string $cell): string => strtolower(trim($cell)),
            str_getcsv(rtrim($headerLine, "\r\n"), $delimiter)
        );

        $lineNumber = 1;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv(rtrim($line, "\r\n"), $delimiter);
            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $value = $cells[$index] ?? null;
                $row[$header] = is_string($value) ? trim($value) : $value;
                if ($row[$header] === '') {
                    $row[$header] = null;
                }
            }

            yield $lineNumber => $row;
        }

        fclose($handle);
    }

    public function exists(string $directory, string $filename): bool
    {
        return is_readable(rtrim($directory, '/').'/'.$filename);
    }
}
