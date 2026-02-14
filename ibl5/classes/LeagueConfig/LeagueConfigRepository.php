<?php

declare(strict_types=1);

namespace LeagueConfig;

use LeagueConfig\Contracts\LeagueConfigRepositoryInterface;

/**
 * @phpstan-import-type LeagueConfigRow from LeagueConfigRepositoryInterface
 *
 * @see LeagueConfigRepositoryInterface For the interface contract
 */
class LeagueConfigRepository extends \BaseMysqliRepository implements LeagueConfigRepositoryInterface
{
    /**
     * @see LeagueConfigRepositoryInterface::hasConfigForSeason()
     */
    public function hasConfigForSeason(int $seasonEndingYear): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM ibl_league_config WHERE season_ending_year = ?',
            'i',
            $seasonEndingYear,
        );

        if ($row === null) {
            return false;
        }

        /** @var int $total */
        $total = $row['total'];

        return $total > 0;
    }

    /**
     * @see LeagueConfigRepositoryInterface::upsertSeasonConfig()
     */
    public function upsertSeasonConfig(int $seasonEndingYear, array $rows): int
    {
        $affectedTotal = 0;

        $query = <<<'SQL'
            INSERT INTO ibl_league_config
                (season_ending_year, team_slot, team_name, conference, division,
                 playoff_qualifiers_per_conf, playoff_round1_format, playoff_round2_format,
                 playoff_round3_format, playoff_round4_format, team_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                team_name = VALUES(team_name),
                conference = VALUES(conference),
                division = VALUES(division),
                playoff_qualifiers_per_conf = VALUES(playoff_qualifiers_per_conf),
                playoff_round1_format = VALUES(playoff_round1_format),
                playoff_round2_format = VALUES(playoff_round2_format),
                playoff_round3_format = VALUES(playoff_round3_format),
                playoff_round4_format = VALUES(playoff_round4_format),
                team_count = VALUES(team_count)
            SQL;

        foreach ($rows as $row) {
            $affected = $this->execute(
                $query,
                'iisssissssi',
                $seasonEndingYear,
                $row['team_slot'],
                $row['team_name'],
                $row['conference'],
                $row['division'],
                $row['playoff_qualifiers_per_conf'],
                $row['playoff_round1_format'],
                $row['playoff_round2_format'],
                $row['playoff_round3_format'],
                $row['playoff_round4_format'],
                $row['team_count'],
            );
            $affectedTotal += $affected;
        }

        return $affectedTotal;
    }

    /**
     * @see LeagueConfigRepositoryInterface::getConfigForSeason()
     * @return list<LeagueConfigRow>
     */
    public function getConfigForSeason(int $seasonEndingYear): array
    {
        /** @var list<LeagueConfigRow> */
        return $this->fetchAll(
            'SELECT * FROM ibl_league_config WHERE season_ending_year = ? ORDER BY team_slot ASC',
            'i',
            $seasonEndingYear,
        );
    }
}
