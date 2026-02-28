<?php

declare(strict_types=1);

namespace Updater\Steps;

use LeagueConfig\LeagueConfigRepository;
use LeagueConfig\LeagueConfigService;
use LeagueConfig\LeagueConfigView;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Step 1: Import league config from .lge file.
 *
 * Skips if already imported for the current season or if the file is missing.
 */
class ImportLeagueConfigStep implements PipelineStepInterface
{
    public function __construct(
        private readonly LeagueConfigRepository $repository,
        private readonly LeagueConfigService $service,
        private readonly LeagueConfigView $view,
        private readonly int $seasonEndingYear,
        private readonly string $lgePath,
    ) {
    }

    public function getLabel(): string
    {
        return 'League config';
    }

    public function execute(): StepResult
    {
        if ($this->repository->hasConfigForSeason($this->seasonEndingYear)) {
            return StepResult::skipped($this->getLabel(), 'Already imported for ' . $this->seasonEndingYear);
        }

        if (!is_file($this->lgePath)) {
            return StepResult::skipped($this->getLabel(), 'No IBL5.lge file found (skipped)');
        }

        $lgeResult = $this->service->processLgeFile($this->lgePath);
        $inlineHtml = $this->view->renderParseResult($lgeResult);

        if (!$lgeResult['success']) {
            $error = is_string($lgeResult['error'] ?? null) ? $lgeResult['error'] : 'Unknown error';
            return StepResult::failure($this->getLabel() . ' import failed', $error);
        }

        $discrepancies = $this->service->crossCheckWithFranchiseSeasons(
            $lgeResult['season_ending_year'],
        );
        if ($discrepancies !== []) {
            $inlineHtml .= $this->view->renderCrossCheckResults($discrepancies);
        }

        return StepResult::success($this->getLabel() . ' imported', inlineHtml: $inlineHtml);
    }
}
