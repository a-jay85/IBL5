<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\Boxscore;
use Boxscore\BoxscoreRepository;
use Boxscore\GameLineWriter;
use JsbParser\ScoFileParser;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * @covers \Boxscore\GameLineWriter
 */
class GameLineWriterTest extends TestCase
{
    /** @var list<array{0: 'team'|'player', 1: array<int|string, mixed>}> */
    private array $calls = [];

    private const GAME_INFO_LINE = '0014' . '00' . '00' . '01' . '18000' . '20000'
        . '1005' . '0510' . '025030028027000' . '022031025030000';

    private const VISITOR_TOTAL_STATS = '3508004003020605060302010203';
    private const HOME_TOTAL_STATS = '3207003002020504050302010203';
    private const VISITOR_PLAYER_STATS = '0801500030201030402010102';
    private const HOME_PLAYER_STATS = '0701200020201020301010102';
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    private static function slot(string $name, string $pos, string $pid, string $minutes, string $stats): string
    {
        // substr() cuts the 28-char team-total stat strings to the real 27-char field
        return substr(str_pad(str_pad($name, 16) . $pos . $pid . $minutes . $stats, 53), 0, 53);
    }

    /** @param array<int, string> $slots slot index => 53-byte slot */
    private static function buildLine(array $slots): string
    {
        $line = self::GAME_INFO_LINE;
        for ($i = 0; $i < ScoFileParser::PLAYER_SLOT_COUNT; $i++) {
            $line .= $slots[$i] ?? str_repeat(' ', 53);
        }
        return str_pad($line, 2000);
    }

    /** @return array<int, string> */
    private static function standardSlots(): array
    {
        return [
            0 => self::slot('Visitor Total', '  ', '000000', '00', self::VISITOR_TOTAL_STATS),
            1 => self::slot('Test Player', 'PG', '200001', '32', self::VISITOR_PLAYER_STATS),
            15 => self::slot('Home Total', '  ', '000000', '00', self::HOME_TOTAL_STATS),
            16 => self::slot('Home Player', 'SG', '200002', '28', self::HOME_PLAYER_STATS),
        ];
    }

    private static function boxscore(): Boxscore
    {
        return Boxscore::withGameInfoLine(self::GAME_INFO_LINE, 2026, 'Regular Season/Playoffs');
    }

    private function recordingWriter(int $teamRows, int $playerRows): GameLineWriter
    {
        $repository = self::createMock(BoxscoreRepository::class);
        $repository->expects(self::exactly($teamRows))->method('insertTeamBoxscore')
            ->willReturnCallback(function (array $row): int {
                $this->calls[] = ['team', $row];
                return 1;
            });
        $repository->expects(self::exactly($playerRows))->method('insertPlayerBoxscore')
            ->willReturnCallback(function (mixed ...$args): int {
                $this->calls[] = ['player', $args];
                return 1;
            });
        return new GameLineWriter(new MockDatabase(), $repository);
    }

    /**
     * An all-spaces 2000-byte record has no player names, so write() returns 0
     * and issues no repository inserts.
     */
    public function testWriteReturnsZeroAndIssuesNoRepositoryWriteForAllSpacesRecord(): void
    {
        $mockDb = new MockDatabase();

        $repository = self::createMock(BoxscoreRepository::class);
        $repository->expects(self::never())->method('insertTeamBoxscore');
        $repository->expects(self::never())->method('insertPlayerBoxscore');

        $writer = new GameLineWriter($mockDb, $repository);
        $line = str_repeat(' ', 2000);
        $boxscore = Boxscore::withGameInfoLine(str_repeat(' ', 58), 2026, 'Regular Season/Playoffs');

        $result = $writer->write($line, $boxscore);

        self::assertSame(0, $result);
    }

    public function testWriteAppliesBothTeamTotalOverridesInVisitorThenHomeOrder(): void
    {
        $writer = $this->recordingWriter(2, 2);

        $writer->write(self::buildLine(self::standardSlots()), self::boxscore(), 'All-Stars Away', 'All-Stars Home');

        self::assertSame('All-Stars Away', $this->calls[0][1]['name']);
        self::assertSame('All-Stars Home', $this->calls[2][1]['name']);
    }

    public function testWriteKeepsParsedTeamTotalNamesWhenOverridesAreNull(): void
    {
        $writer = $this->recordingWriter(2, 2);

        $writer->write(self::buildLine(self::standardSlots()), self::boxscore());

        self::assertSame('Visitor Total', $this->calls[0][1]['name']);
        self::assertSame('Home Total', $this->calls[2][1]['name']);
    }

    public function testWriteAppliesOnlyVisitorOverrideWhenHomeOverrideIsNull(): void
    {
        $writer = $this->recordingWriter(2, 2);

        $writer->write(self::buildLine(self::standardSlots()), self::boxscore(), 'V-Override', null);

        self::assertSame('V-Override', $this->calls[0][1]['name']);
        self::assertSame('Home Total', $this->calls[2][1]['name']);
    }

    public function testWriteAppliesVisitorOverrideToFirstNonEmptyTeamTotalEvenInHomeHalf(): void
    {
        $writer = $this->recordingWriter(1, 0);
        $slots = [15 => self::slot('Home Total', '  ', '000000', '00', self::HOME_TOTAL_STATS)];

        $result = $writer->write(self::buildLine($slots), self::boxscore(), 'V-Override', 'H-Override');

        self::assertSame(1, $result);
        self::assertSame('V-Override', $this->calls[0][1]['name']);
    }

    public function testWriteNeverAppliesTeamNameOverridesToPlayerRows(): void
    {
        $writer = $this->recordingWriter(2, 2);

        $writer->write(self::buildLine(self::standardSlots()), self::boxscore(), 'All-Stars Away', 'All-Stars Home');

        self::assertSame('Test Player', $this->calls[1][1][2]);
        self::assertSame('Home Player', $this->calls[3][1][2]);
    }

    public function testWriteConvertsIso88591PlayerNameToUtf8(): void
    {
        $writer = $this->recordingWriter(0, 1);
        $slots = [1 => self::slot("Jos\xE9 Cruz", 'PG', '200001', '32', self::VISITOR_PLAYER_STATS)];

        $writer->write(self::buildLine($slots), self::boxscore());

        self::assertSame('José Cruz', $this->calls[0][1][2]);
    }

    public function testWriteAssignsVisitorTeamIdThroughSlot14AndHomeTeamIdFromSlot15(): void
    {
        $writer = $this->recordingWriter(0, 2);
        $boxscore = self::boxscore();
        $slots = [
            14 => self::slot('Slot Fourteen', 'PG', '200014', '30', self::VISITOR_PLAYER_STATS),
            15 => self::slot('Slot Fifteen', 'SG', '200015', '30', self::HOME_PLAYER_STATS),
        ];

        $writer->write(self::buildLine($slots), $boxscore);

        self::assertNotSame($boxscore->visitor_teamid, $boxscore->home_teamid);
        self::assertSame(200014, $this->calls[0][1][4]);
        self::assertSame($boxscore->visitor_teamid, $this->calls[0][1][14]);
        self::assertSame(200015, $this->calls[1][1][4]);
        self::assertSame($boxscore->home_teamid, $this->calls[1][1][14]);
    }

    public function testWriteReturnsCountOfTeamAndPlayerRowsInserted(): void
    {
        $writer = $this->recordingWriter(2, 2);

        $result = $writer->write(self::buildLine(self::standardSlots()), self::boxscore());

        self::assertSame(4, $result);
    }

    public function testWriteSkipsSlotWithEmptyNameEvenWithNonZeroPlayerId(): void
    {
        $writer = $this->recordingWriter(0, 1);
        $slots = [
            1 => self::slot('Test Player', 'PG', '200001', '32', self::VISITOR_PLAYER_STATS),
            2 => self::slot('', 'PG', '200003', '30', self::VISITOR_PLAYER_STATS),
        ];

        $result = $writer->write(self::buildLine($slots), self::boxscore());

        self::assertSame(1, $result);
    }

    public function testWriteCallsRepositoryInSlotOrder(): void
    {
        $writer = $this->recordingWriter(2, 2);

        $writer->write(self::buildLine(self::standardSlots()), self::boxscore());

        self::assertSame(['team', 'player', 'team', 'player'], array_column($this->calls, 0));
        self::assertSame('Test Player', $this->calls[1][1][2]);
        self::assertSame('Home Player', $this->calls[3][1][2]);
    }

    public function testWriteBuildsExactTeamBoxscoreRow(): void
    {
        $writer = $this->recordingWriter(2, 2);
        $boxscore = self::boxscore();

        $writer->write(self::buildLine(self::standardSlots()), $boxscore);

        self::assertSame([
            'game_date' => $boxscore->gameDate,
            'name' => 'Visitor Total',
            'game_of_that_day' => $boxscore->game_of_that_day,
            'visitor_teamid' => $boxscore->visitor_teamid,
            'home_teamid' => $boxscore->home_teamid,
            'attendance' => 18000,
            'capacity' => 20000,
            'visitor_wins' => 10,
            'visitor_losses' => 5,
            'home_wins' => 5,
            'home_losses' => 10,
            'visitor_q1_points' => 25,
            'visitor_q2_points' => 30,
            'visitor_q3_points' => 28,
            'visitor_q4_points' => 27,
            'visitor_ot_points' => 0,
            'home_q1_points' => 22,
            'home_q2_points' => 31,
            'home_q3_points' => 25,
            'home_q4_points' => 30,
            'home_ot_points' => 0,
            'game_2gm' => 35,
            'game_2ga' => 80,
            'game_ftm' => 4,
            'game_fta' => 0,
            'game_3gm' => 30,
            'game_3ga' => 20,
            'game_orb' => 60,
            'game_drb' => 50,
            'game_ast' => 60,
            'game_stl' => 30,
            'game_tov' => 20,
            'game_blk' => 10,
            'game_pf' => 20,
        ], $this->calls[0][1]);
    }

    public function testWriteBuildsExactPlayerBoxscoreArguments(): void
    {
        $writer = $this->recordingWriter(2, 2);
        $boxscore = self::boxscore();

        $writer->write(self::buildLine(self::standardSlots()), $boxscore);

        $args = $this->calls[1][1];
        self::assertIsString($args[1]);
        self::assertMatchesRegularExpression(self::UUID_PATTERN, $args[1]);
        $args[1] = '<uuid>';
        self::assertSame([
            $boxscore->gameDate,
            '<uuid>',
            'Test Player',
            'PG',
            200001,
            $boxscore->visitor_teamid,
            $boxscore->home_teamid,
            $boxscore->game_of_that_day,
            18000,
            20000,
            10,
            5,
            5,
            10,
            $boxscore->visitor_teamid,
            32,
            8, 15, 0, 3, 2, 1, 3, 4, 2, 1, 1, 2, 0,
        ], $args);
    }

    public function testWriteGeneratesDistinctUuidPerPlayerRow(): void
    {
        $writer = $this->recordingWriter(2, 2);

        $writer->write(self::buildLine(self::standardSlots()), self::boxscore());

        $first = $this->calls[1][1][1];
        $second = $this->calls[3][1][1];
        self::assertIsString($first);
        self::assertIsString($second);
        self::assertMatchesRegularExpression(self::UUID_PATTERN, $first);
        self::assertMatchesRegularExpression(self::UUID_PATTERN, $second);
        self::assertNotSame($first, $second);
    }
}
