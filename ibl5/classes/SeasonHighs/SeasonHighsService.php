<?php

declare(strict_types=1);

namespace SeasonHighs;

use SeasonHighs\Contracts\SeasonHighsServiceInterface;
use SeasonHighs\Contracts\SeasonHighsRepositoryInterface;

/**
 * SeasonHighsService - Business logic for season highs
 *
 * Calculates date ranges and retrieves season high stats.
 *
 * @phpstan-import-type SeasonHighsData from SeasonHighsServiceInterface
 *
 * @see SeasonHighsServiceInterface For the interface contract
 */
class SeasonHighsService implements SeasonHighsServiceInterface
{
    private SeasonHighsRepositoryInterface $repository;
    private \Season $season;

    /**
     * Stat definitions with SQL expressions and display names.
     *
     * @var array<string, string>
     */
    private const STATS = [
        'POINTS' => '(`game2GM`*2) + `gameFTM` + (`game3GM`*3)',
        'REBOUNDS' => '(`gameORB` + `gameDRB`)',
        'ASSISTS' => '`gameAST`',
        'STEALS' => '`gameSTL`',
        'BLOCKS' => '`gameBLK`',
        'TURNOVERS' => '`gameTOV`',
        'Field Goals Made' => '(`game2GM` + `game3GM`)',
        'Free Throws Made' => '`gameFTM`',
        'Three Pointers Made' => '`game3GM`',
    ];

    public function __construct(SeasonHighsRepositoryInterface $repository, \Season $season)
    {
        $this->repository = $repository;
        $this->season = $season;
    }

    /**
     * @see SeasonHighsServiceInterface::getSeasonHighsData()
     *
     * @return SeasonHighsData
     */
    public function getSeasonHighsData(string $seasonPhase): array
    {
        $dateRange = $this->getDateRangeForPhase($seasonPhase);

        $playerHighs = [];
        $teamHighs = [];

        foreach (self::STATS as $statName => $statExpression) {
            $playerHighs[$statName] = $this->repository->getSeasonHighs(
                $statExpression,
                $statName,
                '',
                $dateRange['start'],
                $dateRange['end']
            );

            $teamHighs[$statName] = $this->repository->getSeasonHighs(
                $statExpression,
                $statName,
                '_teams',
                $dateRange['start'],
                $dateRange['end']
            );
        }

        return [
            'playerHighs' => $playerHighs,
            'teamHighs' => $teamHighs,
        ];
    }

    /**
     * Get date range for a season phase.
     *
     * @param string $seasonPhase Season phase
     * @return array{start: string, end: string} Date range
     */
    private function getDateRangeForPhase(string $seasonPhase): array
    {
        $beginningYear = $this->season->beginningYear;
        $endingYear = $this->season->endingYear;

        switch ($seasonPhase) {
            case 'Playoffs':
                return [
                    'start' => sprintf('%d-%02d-01', $endingYear, \Season::IBL_PLAYOFF_MONTH),
                    'end' => sprintf('%d-%02d-30', $endingYear, \Season::IBL_PLAYOFF_MONTH),
                ];

            case 'Preseason':
                return [
                    'start' => sprintf('%d-%02d-01', \Season::IBL_PRESEASON_YEAR, \Season::IBL_REGULAR_SEASON_STARTING_MONTH),
                    'end' => sprintf('%d-%02d-30', \Season::IBL_PRESEASON_YEAR + 1, \Season::IBL_REGULAR_SEASON_ENDING_MONTH),
                ];

            case 'HEAT':
                return [
                    'start' => sprintf('%d-%02d-01', $beginningYear, \Season::IBL_HEAT_MONTH),
                    'end' => sprintf('%d-%02d-30', $beginningYear, \Season::IBL_HEAT_MONTH),
                ];

            default: // Regular Season
                return [
                    'start' => sprintf('%d-%02d-01', $beginningYear, \Season::IBL_REGULAR_SEASON_STARTING_MONTH),
                    'end' => sprintf('%d-%02d-30', $endingYear, \Season::IBL_REGULAR_SEASON_ENDING_MONTH),
                ];
        }
    }
}
