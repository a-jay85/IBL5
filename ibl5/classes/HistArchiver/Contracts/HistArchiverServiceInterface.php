<?php

declare(strict_types=1);

namespace HistArchiver\Contracts;

use HistArchiver\HistArchiveResult;
use HistArchiver\PlrValidationReport;

interface HistArchiverServiceInterface
{
    /**
     * Archive season stats from box scores to ibl_hist.
     * Skips if no champion has been crowned for the given year.
     */
    public function archiveSeason(int $year): HistArchiveResult;

    /**
     * Compare ibl_hist game stats against box score aggregates for validation.
     */
    public function validatePlrVsBoxScores(int $year): PlrValidationReport;
}
