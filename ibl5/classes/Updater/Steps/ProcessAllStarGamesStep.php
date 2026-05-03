<?php

declare(strict_types=1);

namespace Updater\Steps;

use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreRepository;
use Boxscore\BoxscoreView;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Step 9: Process All-Star games from .sco file.
 *
 * Reads .sco data via the archive-first resolver. Skips if no .sco source is available.
 * Also checks for All-Star team renaming needs.
 */
class ProcessAllStarGamesStep implements PipelineStepInterface
{
    public function __construct(
        private readonly BoxscoreProcessor $processor,
        private readonly BoxscoreRepository $boxscoreRepo,
        private readonly BoxscoreView $view,
        private readonly JsbSourceResolverInterface $sourceResolver,
    ) {
    }

    public function getLabel(): string
    {
        return 'All-Star games processed';
    }

    public function execute(): StepResult
    {
        $data = $this->sourceResolver->getContents('sco');
        if ($data === null) {
            return StepResult::skipped('All-Star games', 'No IBL5.sco file found (skipped)');
        }

        $allStarResult = $this->processor->processAllStarGamesData($data, 0);
        $inlineHtml = $this->view->renderAllStarLog($allStarResult);

        $pendingDefaults = $this->boxscoreRepo->findAllStarGamesWithDefaultNames();
        if ($pendingDefaults !== []) {
            $pendingRenames = [];
            foreach ($pendingDefaults as $row) {
                /** @var string $date */
                $date = $row['game_date'];
                $teamid = $row['name'] === BoxscoreProcessor::DEFAULT_AWAY_NAME
                    ? 50
                    : 51;
                $teamLabel = $teamid === 50 ? 'Away (Visitor)' : 'Home';
                $seasonYear = (int) substr($date, 0, 4);
                $players = $this->boxscoreRepo->getPlayersForAllStarTeam($date, $teamid);

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
