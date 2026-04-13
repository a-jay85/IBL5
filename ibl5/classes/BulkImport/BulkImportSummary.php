<?php

declare(strict_types=1);

namespace BulkImport;

use JsbParser\JsbImportResult;

/**
 * Aggregates results across all file types and entries in a bulk import run.
 */
final class BulkImportSummary
{
    public int $filesProcessed = 0;
    public int $totalInserted = 0;
    public int $totalUpdated = 0;
    public int $totalSkipped = 0;
    public int $totalErrors = 0;

    /** @var list<string> */
    public array $errorMessages = [];

    public function addResult(JsbImportResult $result): void
    {
        $this->filesProcessed++;
        $this->totalInserted += $result->inserted;
        $this->totalUpdated += $result->updated;
        $this->totalSkipped += $result->skipped;
        $this->totalErrors += $result->errors;

        foreach ($result->messages as $msg) {
            if (str_starts_with($msg, 'ERROR:')) {
                $this->errorMessages[] = $msg;
            }
        }
    }

    public function addError(string $message): void
    {
        $this->totalErrors++;
        $this->errorMessages[] = $message;
    }

    public function hasErrors(): bool
    {
        return $this->totalErrors > 0;
    }

    /**
     * Print a formatted summary to stdout.
     */
    public function printSummary(string $title = 'BULK IMPORT COMPLETE'): void
    {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo $title . "\n";
        echo str_repeat('=', 50) . "\n";
        echo sprintf("Files processed:   %d\n", $this->filesProcessed);
        echo sprintf("Records inserted:  %d\n", $this->totalInserted);
        echo sprintf("Records updated:   %d\n", $this->totalUpdated);
        echo sprintf("Records skipped:   %d\n", $this->totalSkipped);
        if ($this->totalErrors > 0) {
            echo sprintf("Errors:            %d\n", $this->totalErrors);
        }
        echo str_repeat('=', 50) . "\n";
    }
}
