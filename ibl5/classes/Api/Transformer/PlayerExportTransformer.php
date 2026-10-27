<?php

declare(strict_types=1);

namespace Api\Transformer;

use Api\Contracts\TransformerInterface;

/**
 * Transforms player export rows to flat CSV-ready string arrays.
 *
 * @phpstan-import-type PlayerExportRow from \Api\Repository\ApiPlayerRepository
 * @implements TransformerInterface<PlayerExportRow>
 */
class PlayerExportTransformer implements TransformerInterface
{
    /** @var list<string> Column keys in output order */
    private const COLUMN_MAP = [
        'pid',
        'name',
        'age',
        'position',
        'htft',
        'htin',
        'dc_can_play_in_game',
        'injured',
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
        'r_fga',
        'r_fgp',
        'r_fta',
        'r_ftp',
        'r_3ga',
        'r_3gp',
        'r_orb',
        'r_drb',
        'r_ast',
        'r_stl',
        'r_tvr',
        'r_blk',
        'r_foul',
        'oo',
        'r_drive_off',
        'po',
        'r_trans_off',
        'od',
        'dd',
        'pd',
        'td',
        'stamina',
        'clutch',
        'consistency',
        'talent',
        'skill',
        'intangibles',
        'loyalty',
        'playing_time',
        'winner',
        'tradition',
        'security',
    ];

    /** @var array<string, string> DB column → CSV header */
    private const HEADERS = [
        'pid' => 'PID',
        'name' => 'Name',
        'age' => 'Age',
        'position' => 'Position',
        'htft' => 'Height (ft)',
        'htin' => 'Height (in)',
        'dc_can_play_in_game' => 'Active',
        'injured' => 'Injury Days',
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
        'three_pointers_made' => '3GM',
        'three_pointers_attempted' => '3GA',
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
        'three_pt_percentage' => '3G%',
        'r_fga' => 'r2GA',
        'r_fgp' => 'r2G%',
        'r_fta' => 'rFTA',
        'r_ftp' => 'rFT%',
        'r_3ga' => 'r3GA',
        'r_3gp' => 'r3G%',
        'r_orb' => 'rORB',
        'r_drb' => 'rDRB',
        'r_ast' => 'rAST',
        'r_stl' => 'rSTL',
        'r_tvr' => 'rTVR',
        'r_blk' => 'rBLK',
        'r_foul' => 'rFOUL',
        'oo' => 'rOO',
        'r_drive_off' => 'rDO',
        'po' => 'rPO',
        'r_trans_off' => 'rTO',
        'od' => 'rOD',
        'dd' => 'rDD',
        'pd' => 'rPD',
        'td' => 'rTD',
        'stamina' => 'Stamina',
        'clutch' => 'Clutch',
        'consistency' => 'Consistency',
        'talent' => 'Talent',
        'skill' => 'Skill',
        'intangibles' => 'Intangibles',
        'loyalty' => 'FA Loyalty',
        'playing_time' => 'FA Playing Time',
        'winner' => 'FA Play for Winner',
        'tradition' => 'FA Tradition',
        'security' => 'FA Security',
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
     * @param PlayerExportRow $row
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
