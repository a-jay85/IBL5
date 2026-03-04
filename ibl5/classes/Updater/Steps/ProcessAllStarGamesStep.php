<?php

declare(strict_types=1);

namespace Updater\Steps;

use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreRepository;
use Boxscore\BoxscoreView;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Step 9: Process All-Star games from .sco file.
 *
 * Skips if the .sco file is missing. Also checks for All-Star team renaming needs.
 */
class ProcessAllStarGamesStep implements PipelineStepInterface
{
    public function __construct(
        private readonly BoxscoreProcessor $processor,
        private readonly BoxscoreRepository $boxscoreRepo,
        private readonly BoxscoreView $view,
        private readonly string $scoPath,
    ) {
    }

    public function getLabel(): string
    {
        return 'All-Star games processed';
    }

    public function execute(): StepResult
    {
        if (!is_file($this->scoPath)) {
            return StepResult::skipped('All-Star games', 'No IBL5.sco file found (skipped)');
        }

        $allStarResult = $this->processor->processAllStarGames($this->scoPath, 0);
        $inlineHtml = $this->view->renderAllStarLog($allStarResult);

        $pendingDefaults = $this->boxscoreRepo->findAllStarGamesWithDefaultNames();
        if ($pendingDefaults !== []) {
            $pendingRenames = [];
            foreach ($pendingDefaults as $row) {
                /** @var string $date */
                $date = $row['Date'];
                $teamID = $row['name'] === BoxscoreProcessor::DEFAULT_AWAY_NAME
                    ? 50
                    : 51;
                $teamLabel = $teamID === 50 ? 'Away (Visitor)' : 'Home';
                $seasonYear = (int) substr($date, 0, 4);
                $players = $this->boxscoreRepo->getPlayersForAllStarTeam($date, $teamID);

                $pendingRenames[] = [
                    'id' => $row['id'],
                    'date' => $date,
                    'name' => $row['name'],
                    'seasonYear' => $seasonYear,
                    'teamLabel' => $teamLabel,
                    'players' => $players,
                ];
            }
            $inlineHtml .= $this->view->renderAllStarRenameUI($pendingRenames);
        }

        return StepResult::success($this->getLabel(), inlineHtml: $inlineHtml);
    }
}
