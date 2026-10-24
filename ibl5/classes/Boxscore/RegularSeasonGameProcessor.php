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
    private const TEAM_NAME_MAX_LENGTH = 16;

    /** @var array<string, string|null> */
    private array $franchiseNameCache = [];

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

        $visitorTeamName = $this->franchiseTeamName($boxscoreGameInfo->visitor_teamid, $seasonEndingYear);
        $homeTeamName = $this->franchiseTeamName($boxscoreGameInfo->home_teamid, $seasonEndingYear);
        $linesProcessed = $this->writer->write($line, $boxscoreGameInfo, $visitorTeamName, $homeTeamName);

        return ['action' => $upsertAction, 'linesProcessed' => $linesProcessed, 'messages' => []];
    }

    /**
     * Look up a franchise's team name once per (season, franchise) for the life of this processor
     */
    private function franchiseTeamName(int $franchiseId, int $seasonEndingYear): ?string
    {
        $key = $seasonEndingYear . ':' . $franchiseId;
        if (!array_key_exists($key, $this->franchiseNameCache)) {
            $name = $this->repository->findFranchiseTeamName($franchiseId, $seasonEndingYear);
            // ibl_box_scores_teams.name is VARCHAR(16); a longer name falls back to the parsed .sco name
            $this->franchiseNameCache[$key] = $name !== null && mb_strlen($name) > self::TEAM_NAME_MAX_LENGTH ? null : $name;
        }

        return $this->franchiseNameCache[$key];
    }
}
