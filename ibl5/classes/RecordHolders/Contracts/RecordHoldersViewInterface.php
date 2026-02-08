<?php

declare(strict_types=1);

namespace RecordHolders\Contracts;

/**
 * View interface for Record Holders module rendering.
 *
 * Provides method to render the all-time IBL records page.
 *
 * @phpstan-import-type AllRecordsData from RecordHoldersServiceInterface
 */
interface RecordHoldersViewInterface
{
    /**
     * Render the complete record holders page.
     *
     * @param AllRecordsData $records Structured record data from the service
     * @return string HTML output for the record holders page
     */
    public function render(array $records): string;
}
