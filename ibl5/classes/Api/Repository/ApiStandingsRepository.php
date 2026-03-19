<?php

declare(strict_types=1);

namespace Api\Repository;

/**
 * @phpstan-type StandingsViewRow array{teamid: int, team_uuid: string, team_city: string, team_name: string, full_team_name: string, conference: string, division: string, league_record: string, conference_record: string, division_record: string, home_record: string, away_record: string, win_percentage: float|null, conference_games_back: string|null, division_games_back: string|null, games_remaining: int, clinched_conference: int, clinched_division: int, clinched_playoffs: int, ...}
 */
class ApiStandingsRepository extends \BaseMysqliRepository
{
    /**
     * Get all standings, optionally filtered by conference.
     *
     * @return list<StandingsViewRow>
     */
    public function getStandings(?string $conference = null): array
    {
        if ($conference !== null) {
            /** @var list<StandingsViewRow> */
            return $this->fetchAll(
                'SELECT * FROM vw_team_standings WHERE conference = ? ORDER BY win_percentage DESC, full_team_name ASC',
                's',
                $conference
            );
        }

        /** @var list<StandingsViewRow> */
        return $this->fetchAll(
            'SELECT * FROM vw_team_standings ORDER BY conference ASC, win_percentage DESC, full_team_name ASC'
        );
    }
}
