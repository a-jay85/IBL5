<?php

declare(strict_types=1);

namespace SeasonArchive;

use SeasonArchive\Contracts\SeasonArchiveRepositoryInterface;
use SeasonArchive\Contracts\SeasonArchiveServiceInterface;

/**
 * Groups raw playoff result rows into a bracket and reads the IBL Finals outcome.
 *
 * Stateless value transformer. Historical-data shaped: rounds are reported exactly as stored.
 *
 * @phpstan-import-type PlayoffSeries from SeasonArchiveServiceInterface
 * @phpstan-import-type PlayoffRow from SeasonArchiveRepositoryInterface
 */
final class SeasonPlayoffBracketBuilder
{
    /**
     * Build playoff bracket grouped by round
     *
     * @param list<PlayoffRow> $playoffResults
     * @return array<int, list<PlayoffSeries>> Map of round => series list
     */
    public function buildPlayoffBracket(array $playoffResults): array
    {
        $bracket = [];

        foreach ($playoffResults as $result) {
            $round = $result['round'];
            if (!isset($bracket[$round])) {
                $bracket[$round] = [];
            }
            $bracket[$round][] = [
                'winner' => $result['winner'],
                'loser' => $result['loser'],
                'loserGames' => $result['loser_games'],
            ];
        }

        ksort($bracket);

        return $bracket;
    }

    /**
     * Get IBL Finals data (round 4)
     *
     * @param list<PlayoffRow> $playoffResults
     * @return array{winner: string, loser: string, loserGames: int}
     */
    public function getIblFinals(array $playoffResults): array
    {
        foreach ($playoffResults as $result) {
            if ($result['round'] === 4) {
                return [
                    'winner' => $result['winner'],
                    'loser' => $result['loser'],
                    'loserGames' => $result['loser_games'],
                ];
            }
        }

        return ['winner' => '', 'loser' => '', 'loserGames' => 0];
    }

    /**
     * Get IBL champion from playoff results (round 4 winner)
     *
     * @param list<PlayoffRow> $playoffResults
     * @return string IBL champion team name, or empty string
     */
    public function getIblChampionFromPlayoffs(array $playoffResults): string
    {
        foreach ($playoffResults as $result) {
            if ($result['round'] === 4) {
                return $result['winner'];
            }
        }

        return '';
    }
}
