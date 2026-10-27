<?php

declare(strict_types=1);

namespace Tests\Api\Transformer;

use Api\Transformer\PlayerExportTransformer;
use PHPUnit\Framework\TestCase;

class PlayerExportTransformerTest extends TestCase
{
    private PlayerExportTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new PlayerExportTransformer();
    }

    /**
     * @return array<string, mixed>
     */
    private function makePlayerRow(): array
    {
        return [
            'player_uuid' => 'abc-123-def',
            'pid' => 123,
            'name' => 'LeBron James',
            'nickname' => 'King James',
            'position' => 'SF',
            'age' => 39,
            'htft' => 6,
            'htin' => 9,
            'dc_can_play_in_game' => 1,
            'retired' => 0,
            'experience' => 20,
            'bird_rights' => 3,
            'teamid' => 5,
            'team_uuid' => 'team-uuid-456',
            'team_city' => 'Los Angeles',
            'team_name' => 'Lakers',
            'full_team_name' => 'Los Angeles Lakers',
            'owner_name' => 'testuser',
            'contract_year' => 1,
            'current_salary' => 4700,
            'year1_salary' => 4700,
            'year2_salary' => 5000,
            'year3_salary' => 5300,
            'year4_salary' => 0,
            'year5_salary' => 0,
            'year6_salary' => 0,
            'games_played' => 50,
            'minutes_played' => 1750,
            'field_goals_made' => 400,
            'field_goals_attempted' => 800,
            'free_throws_made' => 200,
            'free_throws_attempted' => 250,
            'three_pointers_made' => 80,
            'three_pointers_attempted' => 220,
            'offensive_rebounds' => 30,
            'defensive_rebounds' => 300,
            'assists' => 350,
            'steals' => 60,
            'turnovers' => 150,
            'blocks' => 40,
            'personal_fouls' => 80,
            'fg_percentage' => 0.500,
            'ft_percentage' => 0.800,
            'three_pt_percentage' => 0.364,
            'points_per_game' => 21.6,
            'injured' => 12,
            'r_fga' => 45, 'r_fgp' => 52, 'r_fta' => 30, 'r_ftp' => 75, 'r_3ga' => 20, 'r_3gp' => 36,
            'r_orb' => 10, 'r_drb' => 40, 'r_ast' => 50, 'r_stl' => 15, 'r_tvr' => 25, 'r_blk' => 8, 'r_foul' => 18,
            'oo' => 7, 'r_drive_off' => 8, 'po' => 6, 'r_trans_off' => 9, 'od' => 5, 'dd' => 6, 'pd' => 4, 'td' => 7,
            'stats_gs' => 45, 'wt' => 250, 'draftyear' => 2003, 'draftround' => 1, 'draftpickno' => 1, 'college' => 'St. Vincent-St. Mary', 'clutch' => 2, 'consistency' => 1,
            'talent' => 5, 'skill' => 4, 'intangibles' => 3,
            'loyalty' => 2, 'playing_time' => 4, 'winner' => 5, 'tradition' => 3, 'security' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-15 12:00:00',
        ];
    }

    public function testGetHeadersReturns80Columns(): void
    {
        $headers = $this->transformer->getHeaders();

        $this->assertCount(80, $headers);
        $this->assertSame('PID', $headers[0]);
        $this->assertSame('Name', $headers[1]);
        $this->assertSame('FA Security', $headers[79]);
    }

    public function testHeadersAreUnique(): void
    {
        $headers = $this->transformer->getHeaders();

        $this->assertSame($headers, array_values(array_unique($headers)));
    }

    public function testNoHeaderParsesAsTimeInSpreadsheets(): void
    {
        foreach ($this->transformer->getHeaders() as $header) {
            $this->assertDoesNotMatchRegularExpression('/^\d+\s*[AP]M?$/i', $header);
        }
    }

    public function testHeadersExcludeNickname(): void
    {
        $this->assertNotContains('Nickname', $this->transformer->getHeaders());
    }

    public function testTransformIncludesInjuryRatingsTsiAndFreeAgencyFactors(): void
    {
        $result = $this->transformer->transform($this->makePlayerRow());
        $indexMap = array_flip($this->transformer->getHeaders());

        $this->assertSame('12', $result[$indexMap['Injury Days']]);
        $this->assertSame('45', $result[$indexMap['r2GA']]);
        $this->assertSame('36', $result[$indexMap['r3G%']]);
        $this->assertSame('8', $result[$indexMap['rDO']]);
        $this->assertSame('9', $result[$indexMap['rTO']]);
        $this->assertSame('7', $result[$indexMap['rTD']]);
        $this->assertSame('45', $result[$indexMap['GS']]);
        $this->assertSame('250', $result[$indexMap['Weight']]);
        $this->assertSame('2003', $result[$indexMap['Draft Year']]);
        $this->assertSame('1', $result[$indexMap['Draft Round']]);
        $this->assertSame('1', $result[$indexMap['Draft Pick']]);
        $this->assertSame('St. Vincent-St. Mary', $result[$indexMap['College']]);
        $this->assertSame('5', $result[$indexMap['Talent']]);
        $this->assertSame('4', $result[$indexMap['Skill']]);
        $this->assertSame('3', $result[$indexMap['Intangibles']]);
        $this->assertSame('2', $result[$indexMap['FA Loyalty']]);
        $this->assertSame('4', $result[$indexMap['FA Playing Time']]);
        $this->assertSame('5', $result[$indexMap['FA Play for Winner']]);
        $this->assertSame('3', $result[$indexMap['FA Tradition']]);
        $this->assertSame('1', $result[$indexMap['FA Security']]);
    }

    public function testTransformOutputsEmptyStringForNullRatings(): void
    {
        $row = $this->makePlayerRow();
        $row['injured'] = null;
        $row['talent'] = null;
        $result = $this->transformer->transform($row);
        $indexMap = array_flip($this->transformer->getHeaders());

        $this->assertSame('', $result[$indexMap['Injury Days']]);
        $this->assertSame('', $result[$indexMap['Talent']]);
    }

    public function testTransformOutputMatchesHeaderCount(): void
    {
        $row = $this->makePlayerRow();
        $result = $this->transformer->transform($row);

        $this->assertCount(count($this->transformer->getHeaders()), $result);
    }

    public function testTransformProducesCorrectValues(): void
    {
        $row = $this->makePlayerRow();
        $result = $this->transformer->transform($row);

        $this->assertSame('123', $result[0]); // PID
        $this->assertSame('LeBron James', $result[1]); // Name
        $this->assertSame('39', $result[2]); // Age
        $this->assertSame('SF', $result[3]); // Position
    }

    public function testTransformIncludesFullContractSalaries(): void
    {
        $row = $this->makePlayerRow();
        $result = $this->transformer->transform($row);

        $headers = $this->transformer->getHeaders();
        $indexMap = array_flip($headers);

        $this->assertSame('4700', $result[$indexMap['Current Salary']]);
        $this->assertSame('4700', $result[$indexMap['Year 1 Salary']]);
        $this->assertSame('5000', $result[$indexMap['Year 2 Salary']]);
        $this->assertSame('5300', $result[$indexMap['Year 3 Salary']]);
        $this->assertSame('0', $result[$indexMap['Year 4 Salary']]);
        $this->assertSame('0', $result[$indexMap['Year 5 Salary']]);
        $this->assertSame('0', $result[$indexMap['Year 6 Salary']]);
    }

    public function testTransformOutputsEmptyStringForNullTeamFields(): void
    {
        $row = $this->makePlayerRow();
        $row['teamid'] = null;
        $row['team_uuid'] = null;
        $row['team_city'] = null;
        $row['team_name'] = null;
        $row['full_team_name'] = null;
        $row['owner_name'] = null;
        $result = $this->transformer->transform($row);

        $headers = $this->transformer->getHeaders();
        $indexMap = array_flip($headers);

        $this->assertSame('', $result[$indexMap['Team ID']]);
        $this->assertSame('', $result[$indexMap['Team City']]);
        $this->assertSame('', $result[$indexMap['Team Name']]);
        $this->assertSame('', $result[$indexMap['Full Team Name']]);
        $this->assertSame('', $result[$indexMap['Owner']]);
    }

    public function testTransformOutputsEmptyStringForNullPercentages(): void
    {
        $row = $this->makePlayerRow();
        $row['fg_percentage'] = null;
        $row['ft_percentage'] = null;
        $row['three_pt_percentage'] = null;
        $row['points_per_game'] = null;
        $result = $this->transformer->transform($row);

        $headers = $this->transformer->getHeaders();
        $indexMap = array_flip($headers);

        $this->assertSame('', $result[$indexMap['PPG']]);
        $this->assertSame('', $result[$indexMap['FG%']]);
        $this->assertSame('', $result[$indexMap['FT%']]);
        $this->assertSame('', $result[$indexMap['3G%']]);
    }

    public function testTransformExcludesUuidsAndTimestamps(): void
    {
        $row = $this->makePlayerRow();
        $this->transformer->transform($row);
        $headers = $this->transformer->getHeaders();

        $this->assertNotContains('player_uuid', $headers);
        $this->assertNotContains('team_uuid', $headers);
        $this->assertNotContains('created_at', $headers);
        $this->assertNotContains('updated_at', $headers);
    }
}
