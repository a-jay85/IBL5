<?php

declare(strict_types=1);

namespace Updater\Steps;

use HistArchiver\Contracts\HistArchiverServiceInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

class ArchiveSeasonHistStep implements PipelineStepInterface
{
    public function __construct(
        private readonly HistArchiverServiceInterface $service,
        private readonly int $seasonYear,
    ) {
    }

    public function getLabel(): string
    {
        return 'Season history archived';
    }

    public function execute(): StepResult
    {
        $result = $this->service->archiveSeason($this->seasonYear);

        if ($result->skippedNoChampion) {
            return StepResult::skipped($this->getLabel(), 'No champion yet — playoffs incomplete');
        }

        return StepResult::success(
            label: $this->getLabel(),
            detail: $result->playersArchived . ' players archived',
            messages: $result->messages,
            messageErrorCount: count(array_filter(
                $result->messages,
                static fn (string $msg): bool => str_starts_with($msg, 'WARNING:'),
            )),
        );
    }
}
