<?php

declare(strict_types=1);

namespace Boxscore;

/**
 * GameUpsertResolver - Determines the upsert action for a game and performs deletes if updating
 */
class GameUpsertResolver
{
    public function __construct(
        private BoxscoreRepository $repository,
    ) {
    }

    /**
     * Determine upsert action for a game and perform delete if updating
     *
     * @return string 'insert', 'skip', or 'update'
     */
    public function resolve(Boxscore $boxscoreGameInfo): string
    {
        $existingGame = $this->repository->findTeamBoxscore(
            $boxscoreGameInfo->gameDate,
            $boxscoreGameInfo->visitor_teamid,
            $boxscoreGameInfo->home_teamid,
            $boxscoreGameInfo->game_of_that_day
        );

        if ($existingGame === null) {
            return 'insert';
        }

        /** @var array{visitor_q1_points: int, visitor_q2_points: int, visitor_q3_points: int, visitor_q4_points: int, visitor_ot_points: int, home_q1_points: int, home_q2_points: int, home_q3_points: int, home_q4_points: int, home_ot_points: int} $existingGame */
        $scoresMatch = $boxscoreGameInfo->scoresMatchDatabase($existingGame);

        if ($scoresMatch) {
            // Scores match — but re-import if player records have NULL teamid
            $hasNullTeamId = $this->repository->hasNullTeamIdPlayerBoxscores(
                $boxscoreGameInfo->gameDate,
                $boxscoreGameInfo->visitor_teamid,
                $boxscoreGameInfo->home_teamid
            );

            if (!$hasNullTeamId) {
                return 'skip';
            }
        }

        // Scores differ or player records need teamid fix — delete old records, then re-insert
        $this->repository->deleteTeamBoxscoresByGame(
            $boxscoreGameInfo->gameDate,
            $boxscoreGameInfo->visitor_teamid,
            $boxscoreGameInfo->home_teamid,
            $boxscoreGameInfo->game_of_that_day
        );
        $this->repository->deletePlayerBoxscoresByGame(
            $boxscoreGameInfo->gameDate,
            $boxscoreGameInfo->visitor_teamid,
            $boxscoreGameInfo->home_teamid
        );

        return 'update';
    }
}
