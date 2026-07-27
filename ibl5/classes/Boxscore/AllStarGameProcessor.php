<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\GameTypeProcessorInterface;
use JsbParser\ScoFileParser;
use Season\Season;

/**
 * AllStarGameProcessor - Processes the All-Star game with three distinct outcomes
 *
 * Outcome A: scores match existing record — skip.
 * Outcome B: scores differ — re-insert preserving existing custom names.
 * Outcome C: new game — insert with default placeholder names.
 *
 * @see GameTypeProcessorInterface
 */
class AllStarGameProcessor implements GameTypeProcessorInterface
{
    private const ALL_STAR_VISITOR_TID = 50;
    private const ALL_STAR_HOME_TID = 51;

    public function __construct(
        private GameUpsertResolver $resolver,
        private GameLineWriter $writer,
        private BoxscoreRepository $repository,
    ) {
    }

    /**
     * @see GameTypeProcessorInterface::process()
     *
     * Note: $seasonPhase and $league are deliberately ignored — the All-Star game
     * always uses the hardcoded phase 'Regular Season/Playoffs' with no league argument.
     */
    public function process(string $line, int $seasonEndingYear, string $seasonPhase, string $league): array
    {
        $gameDate = sprintf('%d-%02d-%02d', $seasonEndingYear, Season::IBL_ALL_STAR_MONTH, Season::IBL_ALL_STAR_GAME_DAY);

        $gameInfoLine = ScoFileParser::extractGameInfo($line);
        $boxscoreGameInfo = Boxscore::withGameInfoLine($gameInfoLine, $seasonEndingYear, 'Regular Season/Playoffs');
        $boxscoreGameInfo->overrideGameContext(
            $gameDate,
            self::ALL_STAR_VISITOR_TID,
            self::ALL_STAR_HOME_TID,
            1,
        );

        // Check if team names already exist in DB for this game
        $existingNames = $this->repository->findAllStarTeamNames($gameDate);

        if ($existingNames !== null) {
            // Names already set — check if scores match
            $existingGame = $this->repository->findTeamBoxscore(
                $gameDate,
                self::ALL_STAR_VISITOR_TID,
                self::ALL_STAR_HOME_TID,
                1
            );

            if ($existingGame !== null) {
                /** @var array{visitor_q1_points: int, visitor_q2_points: int, visitor_q3_points: int, visitor_q4_points: int, visitor_ot_points: int, home_q1_points: int, home_q2_points: int, home_q3_points: int, home_q4_points: int, home_ot_points: int} $existingGame */
                if ($boxscoreGameInfo->scoresMatchDatabase($existingGame)) {
                    // Outcome A: scores match — skip
                    return ['action' => 'skip', 'linesProcessed' => 0, 'messages' => ['All-Star Game: already exists with matching scores, skipped.']];
                }

                // Outcome B: scores differ — read existing names before deleting
                $savedAwayName = $existingNames['awayName'];
                $savedHomeName = $existingNames['homeName'];

                $this->repository->deleteTeamBoxscoresByGame($gameDate, self::ALL_STAR_VISITOR_TID, self::ALL_STAR_HOME_TID, 1);
                $this->repository->deletePlayerBoxscoresByGame($gameDate, self::ALL_STAR_VISITOR_TID, self::ALL_STAR_HOME_TID);

                $linesProcessed = $this->writer->write($line, $boxscoreGameInfo, $savedAwayName, $savedHomeName);

                /** @var list<string> $messages */
                $messages = [];
                if ($linesProcessed > 0) {
                    $messages[] = "All-Star Game: updated with existing team names ({$linesProcessed} lines).";
                }

                return ['action' => 'update', 'linesProcessed' => $linesProcessed, 'messages' => $messages];
            }
        }

        // Outcome C: game not in DB — insert with default placeholder names
        $upsertAction = $this->resolver->resolve($boxscoreGameInfo);
        if ($upsertAction === 'skip') {
            return ['action' => 'skip', 'linesProcessed' => 0, 'messages' => ['All-Star Game: already exists, skipped.']];
        }

        $linesProcessed = $this->writer->write(
            $line,
            $boxscoreGameInfo,
            BoxscoreProcessor::DEFAULT_AWAY_NAME,
            BoxscoreProcessor::DEFAULT_HOME_NAME,
        );

        /** @var list<string> $messages */
        $messages = [];
        if ($linesProcessed > 0) {
            $action = $upsertAction === 'update' ? 'updated' : 'inserted';
            $messages[] = "All-Star Game: {$action} ({$linesProcessed} lines).";
        }

        return ['action' => $upsertAction, 'linesProcessed' => $linesProcessed, 'messages' => $messages];
    }
}
