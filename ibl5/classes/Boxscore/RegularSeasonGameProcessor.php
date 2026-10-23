<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\BoxscoreRepositoryInterface;
use Boxscore\Contracts\GameTypeProcessorInterface;
use JsbParser\ScoFileParser;

/**
 * RegularSeasonGameProcessor - Processes a single regular-season .sco game line
 *
 * @see GameTypeProcessorInterface
 */
class RegularSeasonGameProcessor implements GameTypeProcessorInterface
{
    public function __construct(
        private GameUpsertResolver $resolver,
        private GameLineWriter $writer,
        private BoxscoreRepositoryInterface $repository,
    ) {
    }

    /**
     * @see GameTypeProcessorInterface::process()
     */
    public function process(string $line, int $seasonEndingYear, string $seasonPhase, string $league): array
    {
        $gameInfoLine = ScoFileParser::extractGameInfo($line);
        $boxscoreGameInfo = Boxscore::withGameInfoLine($gameInfoLine, $seasonEndingYear, $seasonPhase, $league);

        $upsertAction = $this->resolver->resolve($boxscoreGameInfo);

        if ($upsertAction === 'skip') {
            return ['action' => 'skip', 'linesProcessed' => 0, 'messages' => []];
        }

        $visitorTeamName = $this->repository->findFranchiseTeamName($boxscoreGameInfo->visitor_teamid, $seasonEndingYear);
        $homeTeamName = $this->repository->findFranchiseTeamName($boxscoreGameInfo->home_teamid, $seasonEndingYear);
        $linesProcessed = $this->writer->write($line, $boxscoreGameInfo, $visitorTeamName, $homeTeamName);

        return ['action' => $upsertAction, 'linesProcessed' => $linesProcessed, 'messages' => []];
    }
}
