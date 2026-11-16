<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrExportRepositoryInterface;
use PlrParser\PlrExportService;
use PlrParser\PlrFileWriter;
use PlrParser\PlrWriteResult;

/**
 * Depth-chart writes and trade relocation in PlrExportService.
 *
 * @covers \PlrParser\PlrExportService
 */
class PlrExportServiceDepthTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * @return array{pid: int, name: string, teamid: int, bird: int, cy: int, cyt: int, salary_yr1: int, salary_yr2: int, salary_yr3: int, salary_yr4: int, salary_yr5: int, salary_yr6: int, fa_signing_flag: int}
     */
    private static function dbPlayer(int $pid, int $teamid): array
    {
        return [
            'pid' => $pid,
            'name' => 'Player ' . $pid,
            'teamid' => $teamid,
            'bird' => 0,
            'cy' => 0,
            'cyt' => 0,
            'salary_yr1' => 0,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'fa_signing_flag' => 0,
        ];
    }

    /**
     * @param array<int, array{pid: int, teamid: int}> $players ordinal => player
     * @param array<int, int> $dbTeams pid => DB teamid
     * @param array<int, array{pid: int, teamid: int, dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}> $charts
     * @param list<int> $shortEmptyOrdinals empty slots cut to 44 chars
     * @return array{0: PlrWriteResult, 1: string, 2: string} result, input content, output content
     */
    private function export(array $players, array $dbTeams, array $charts, array $shortEmptyOrdinals = []): array
    {
        $dbPlayers = [];
        foreach ($dbTeams as $pid => $teamid) {
            $dbPlayers[$pid] = self::dbPlayer($pid, $teamid);
        }
        $repo = self::createStub(PlrExportRepositoryInterface::class);
        $repo->method('getAllPlayerChangeableFields')->willReturn($dbPlayers);
        $repo->method('getAllPlayerDepthCharts')->willReturn($charts);

        $lines = JsbExportFixtures::lines(20, $players);
        foreach ($shortEmptyOrdinals as $ordinal) {
            $lines[$ordinal] = substr($lines[$ordinal], 0, 44);
        }
        $content = implode("\r\n", $lines);
        $input = tempnam(sys_get_temp_dir(), 'plr_depth_');
        self::assertIsString($input);
        file_put_contents($input, $content);
        $output = $input . '.out';
        $this->tmpFiles[] = $input;
        $this->tmpFiles[] = $output;

        $result = (new PlrExportService($repo))->exportPlrFile($input, $output);
        $written = file_exists($output) ? (string) file_get_contents($output) : '';

        return [$result, $content, $written];
    }

    private static function lineAt(string $content, int $ordinal): string
    {
        return PlrFileWriter::splitIntoLines($content)[$ordinal];
    }

    public function testDepthAndActiveWrittenForChartedTeam(): void
    {
        [$result, , $output] = $this->export(
            [571 => ['pid' => 501, 'teamid' => 20]],
            [501 => 20],
            [501 => JsbExportFixtures::chartRow(501, 20, ['dc_pg_depth' => 1, 'dc_sf_depth' => 3, 'dc_c_depth' => 2, 'dc_can_play_in_game' => 1, 'dc_minutes' => 30])],
        );

        $line = self::lineAt($output, 571);
        self::assertSame('10302', substr($line, 132, 5));
        self::assertSame('1', substr($line, 137, 1));
        self::assertContains('Depth charts applied for 1 teams', $result->messages);
        self::assertSame(0, $result->errors);
    }

    public function testUnchartedTeamDepthBytesPreserved(): void
    {
        [$result, $input, $output] = $this->export(
            [
                571 => ['pid' => 501, 'teamid' => 20],
                541 => ['pid' => 401, 'teamid' => 19],
            ],
            [501 => 20, 401 => 19],
            [
                501 => JsbExportFixtures::chartRow(501, 20),
                401 => JsbExportFixtures::chartRow(401, 19, ['dc_minutes' => 20, 'dc_pg_depth' => 1]),
            ],
        );

        self::assertSame(self::lineAt($input, 571), self::lineAt($output, 571));
        self::assertNotSame(self::lineAt($input, 541), self::lineAt($output, 541));
        self::assertContains('Depth charts applied for 1 teams', $result->messages);
    }

    public function testDepthSkippedWhenSlotBlockTeamDiffersFromDbTeam(): void
    {
        // pid 502 says team 20 in the file and the DB, but sits in team 19's block (541-570).
        [, $input, $output] = $this->export(
            [
                571 => ['pid' => 501, 'teamid' => 20],
                545 => ['pid' => 502, 'teamid' => 20],
            ],
            [501 => 20, 502 => 20],
            [
                501 => JsbExportFixtures::chartRow(501, 20, ['dc_pg_depth' => 1]),
                502 => JsbExportFixtures::chartRow(502, 20, ['dc_sg_depth' => 1, 'dc_can_play_in_game' => 1]),
            ],
        );

        self::assertSame(self::lineAt($input, 545), self::lineAt($output, 545));
        self::assertSame('1', substr(self::lineAt($output, 571), 132, 1));
    }

    public function testRelocationAppearsInChangeLog(): void
    {
        [$result, , $output] = $this->export(
            [
                541 => ['pid' => 401, 'teamid' => 19],
                571 => ['pid' => 501, 'teamid' => 20],
            ],
            [401 => 20, 501 => 20],
            [],
        );

        self::assertSame(401, (int) trim(substr(self::lineAt($output, 572), 38, 6)));
        self::assertSame(0, (int) trim(substr(self::lineAt($output, 541), 38, 6)));
        self::assertSame(
            [
                'pid' => 401,
                'name' => 'Player 401',
                'changes' => [
                    ['field' => 'ordinal', 'old' => 541, 'new' => 572],
                    ['field' => 'teamid', 'old' => 19, 'new' => 20],
                ],
            ],
            $result->changeLog[0],
        );
    }

    public function testOutputSizeEqualsInputAfterRelocation(): void
    {
        [$result, $input, $output] = $this->export(
            [
                541 => ['pid' => 401, 'teamid' => 19],
                542 => ['pid' => 402, 'teamid' => 19],
                571 => ['pid' => 501, 'teamid' => 20],
            ],
            [401 => 20, 402 => 20, 501 => 19],
            [],
            [572, 573],
        );

        self::assertSame(0, $result->errors);
        self::assertSame(strlen($input), strlen($output));
        self::assertCount(3, $result->changeLog);
        // 401 and 402 take the short slots 572 and 573. 501 then takes 541, which 401 freed.
        self::assertSame(501, (int) trim(substr(self::lineAt($output, 541), 38, 6)));
        self::assertSame(44, strlen(self::lineAt($output, 542)));
        self::assertSame(44, strlen(self::lineAt($output, 571)));
        self::assertSame(PlrFileWriter::PLAYER_RECORD_LENGTH, strlen(self::lineAt($output, 572)));
    }
}
