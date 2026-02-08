<?php

declare(strict_types=1);

namespace Api\Repository;

class ApiStandingsRepository extends \BaseMysqliRepository
{
    /**
     * Get all standings, optionally filtered by conference.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStandings(?string $conference = null): array
    {
        if ($conference !== null) {
            return $this->fetchAll(
                'SELECT * FROM vw_team_standings WHERE conference = ? ORDER BY win_percentage DESC, full_team_name ASC',
                's',
                $conference
            );
        }

        return $this->fetchAll(
            'SELECT * FROM vw_team_standings ORDER BY conference ASC, win_percentage DESC, full_team_name ASC'
        );
    }
}
