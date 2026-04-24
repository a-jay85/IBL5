<?php

declare(strict_types=1);

namespace Api\Transformer;

/**
 * Transforms vw_player_current rows to flat CSV-ready string arrays.
 *
 * @phpstan-import-type PlayerCurrentRow from \Api\Repository\ApiPlayerRepository
 */
class PlayerExportTransformer
{
    /** @var list<string> Column keys in output order */
    private const COLUMN_MAP = [
        'pid',
        'name',
        'nickname',
        'age',
        'position',
        'htft',
        'htin',
        'dc_can_play_in_game',
        'retired',
        'experience',
        'bird_rights',
        'teamid',
        'team_city',
        'team_name',
        'full_team_name',
        'owner_name',
        'contract_year',
        'current_salary',
        'year1_salary',
        'year2_salary',
        'year3_salary',
        'year4_salary',
        'year5_salary',
        'year6_salary',
        'games_played',
        'minutes_played',
        'field_goals_made',
        'field_goals_attempted',
        'free_throws_made',
        'free_throws_attempted',
        'three_pointers_made',
        'three_pointers_attempted',
        'offensive_rebounds',
        'defensive_rebounds',
        'assists',
        'steals',
        'turnovers',
        'blocks',
        'personal_fouls',
        'points_per_game',
        'fg_percentage',
        'ft_percentage',
        'three_pt_percentage',
    ];

    /** @var array<string, string> DB column → CSV header */
    private const HEADERS = [
        'pid' => 'PID',
        'name' => 'Name',
        'nickname' => 'Nickname',
        'age' => 'Age',
        'position' => 'Position',
        'htft' => 'Height (ft)',
        'htin' => 'Height (in)',
        'dc_can_play_in_game' => 'Active',
        'retired' => 'Retired',
        'experience' => 'Experience',
        'bird_rights' => 'Bird Rights',
        'teamid' => 'Team ID',
        'team_city' => 'Team City',
        'team_name' => 'Team Name',
        'full_team_name' => 'Full Team Name',
        'owner_name' => 'Owner',
        'contract_year' => 'Contract Year',
        'current_salary' => 'Current Salary',
        'year1_salary' => 'Year 1 Salary',
        'year2_salary' => 'Year 2 Salary',
        'year3_salary' => 'Year 3 Salary',
        'year4_salary' => 'Year 4 Salary',
        'year5_salary' => 'Year 5 Salary',
        'year6_salary' => 'Year 6 Salary',
        'games_played' => 'GP',
        'minutes_played' => 'MIN',
        'field_goals_made' => 'FGM',
        'field_goals_attempted' => 'FGA',
        'free_throws_made' => 'FTM',
        'free_throws_attempted' => 'FTA',
        'three_pointers_made' => '3PM',
        'three_pointers_attempted' => '3PA',
        'offensive_rebounds' => 'ORB',
        'defensive_rebounds' => 'DRB',
        'assists' => 'AST',
        'steals' => 'STL',
        'turnovers' => 'TO',
        'blocks' => 'BLK',
        'personal_fouls' => 'PF',
        'points_per_game' => 'PPG',
        'fg_percentage' => 'FG%',
        'ft_percentage' => 'FT%',
        'three_pt_percentage' => '3P%',
    ];

    /**
     * @return list<string> Column headers for CSV first row
     */
    public function getHeaders(): array
    {
        return array_map(
            static fn (string $col): string => self::HEADERS[$col],
            self::COLUMN_MAP,
        );
    }

    /**
     * Transform a player row to a flat list of strings for CSV output.
     *
     * @param PlayerCurrentRow $row
     * @return list<string>
     */
    public function transform(array $row): array
    {
        $result = [];
        foreach (self::COLUMN_MAP as $col) {
            $value = $row[$col] ?? null;
            $str = $value !== null ? (string) $value : '';

            // Prevent spreadsheet formula injection (OWASP)
            if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                $str = "'" . $str;
            }

            $result[] = $str;
        }

        return $result;
    }
}
