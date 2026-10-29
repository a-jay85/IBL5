<?php

declare(strict_types=1);

namespace Tests\OnThisDay;

use OnThisDay\Moment;
use OnThisDay\MomentFormatter;
use OnThisDay\MomentKind;
use OnThisDay\MomentSegment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MomentFormatterTest extends TestCase
{
    private MomentFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new MomentFormatter();
    }

    /**
     * @param array<string, int> $facts
     */
    private static function makeMoment(
        MomentKind $kind,
        array $facts,
        ?string $statLabel = null,
        int $gameOfThatDay = 0,
        int $pid = 1,
        string $playerName = 'Test Player',
        string $date = '2019-02-14',
    ): Moment {
        return new Moment(
            kind: $kind,
            key: $kind->value . ':' . $date . ':1',
            date: $date,
            seasonYear: (int) substr($date, 0, 4),
            pid: $pid,
            playerName: $playerName,
            teamId: 1,
            teamName: 'Hawks',
            opponentTeamId: 2,
            opponentName: 'Celtics',
            facts: $facts,
            statLabel: $statLabel,
            gameOfThatDay: $gameOfThatDay,
        );
    }

    /**
     * @return array<string, array{Moment, string}>
     */
    public static function momentKindProvider(): array
    {
        return [
            'fifty point game' => [
                self::makeMoment(MomentKind::FiftyPointGame, ['points' => 52]),
                'Test Player (Hawks) scored 52 points against the Celtics',
            ],
            'triple double' => [
                self::makeMoment(MomentKind::TripleDouble, ['points' => 21, 'rebounds' => 12, 'assists' => 10]),
                'Test Player (Hawks) posted a triple-double against the Celtics: 21 pts, 12 reb, 10 ast',
            ],
            'record game' => [
                self::makeMoment(MomentKind::RecordGame, ['value' => 17], 'steals'),
                'Test Player (Hawks) set the league single-game steals record with 17 against the Celtics',
            ],
            'series clincher' => [
                self::makeMoment(MomentKind::SeriesClincher, ['round' => 3, 'wins' => 4, 'losses' => 2]),
                'The Hawks closed out the Celtics 4-2 in the conference finals',
            ],
            'trade' => [
                self::makeMoment(MomentKind::Trade, ['players_moved' => 1]),
                'Test Player moved from the Celtics to the Hawks',
            ],
        ];
    }

    #[DataProvider('momentKindProvider')]
    public function testDescribesEachMomentKind(Moment $moment, string $expected): void
    {
        $this->assertSame($expected, $this->formatter->describe($moment));
    }

    public function testDescribesMultiPlayerTrade(): void
    {
        $two = self::makeMoment(MomentKind::Trade, ['players_moved' => 2]);
        $four = self::makeMoment(MomentKind::Trade, ['players_moved' => 4]);

        $this->assertSame('Test Player moved from the Celtics to the Hawks with 1 other player', $this->formatter->describe($two));
        $this->assertSame('Test Player moved from the Celtics to the Hawks with 3 other players', $this->formatter->describe($four));
    }

    public function testSeasonsAgoLabelSingularAndPlural(): void
    {
        $this->assertSame('1 season ago', $this->formatter->seasonsAgoLabel(1));
        $this->assertSame('2 seasons ago', $this->formatter->seasonsAgoLabel(2));
        $this->assertSame('10 seasons ago', $this->formatter->seasonsAgoLabel(10));
    }

    public function testUnknownPlayoffRoundFallsBackToPlayoffs(): void
    {
        $moment = self::makeMoment(MomentKind::SeriesClincher, ['round' => 9, 'wins' => 4, 'losses' => 0]);

        $this->assertSame('The Hawks closed out the Celtics 4-0 in the playoffs', $this->formatter->describe($moment));
    }

    public function testRoundLabelsCoverEveryPlayoffRound(): void
    {
        $labels = [];
        foreach ([1, 2, 3, 4] as $round) {
            $labels[] = $this->formatter->describe(
                self::makeMoment(MomentKind::SeriesClincher, ['round' => $round, 'wins' => 4, 'losses' => 1]),
            );
        }

        $this->assertSame([
            'The Hawks closed out the Celtics 4-1 in the first round',
            'The Hawks closed out the Celtics 4-1 in the conference semifinals',
            'The Hawks closed out the Celtics 4-1 in the conference finals',
            'The Hawks closed out the Celtics 4-1 in the IBL Finals',
        ], $labels);
    }

    public function testDiscordLineNeutralizesMentions(): void
    {
        $moment = new Moment(
            kind: MomentKind::FiftyPointGame,
            key: 'fifty_point_game:2019-02-14:1',
            date: '2019-02-14',
            seasonYear: 2019,
            pid: 1,
            playerName: '@everyone',
            teamId: 1,
            teamName: 'Hawks',
            opponentTeamId: 2,
            opponentName: 'Celtics',
            facts: ['points' => 52],
        );

        $line = $this->formatter->discordLine($moment, 2);

        $this->assertStringContainsString("@\u{200B}everyone", $line);
        $this->assertStringNotContainsString('@everyone', $line);
        $this->assertStringStartsWith('On this day in IBL history (2 seasons ago, Feb 14, 2019): ', $line);
        $this->assertLessThanOrEqual(1900, mb_strlen($line));
    }

    public function testDateLabelFormatsMonthDayYear(): void
    {
        $this->assertSame('Feb 14, 2019', $this->formatter->dateLabel('2019-02-14'));
    }

    private const BOX_URL = 'modules.php?name=GameBoxscore&date=1999-03-04&game=3&team=1';
    private const PLAYER_URL = 'modules.php?name=Player&pa=showpage&pid=7';

    /**
     * @return array<string, array{Moment, list<MomentSegment>}>
     */
    public static function segmentKindProvider(): array
    {
        $linked = static fn (MomentKind $kind, array $facts, ?string $label = null, int $pid = 7): Moment => self::makeMoment(
            $kind,
            $facts,
            $label,
            gameOfThatDay: 3,
            pid: $pid,
            date: '1999-03-04',
        );

        return [
            'fifty point game' => [
                $linked(MomentKind::FiftyPointGame, ['points' => 52]),
                [
                    new MomentSegment('Test Player (Hawks)', self::PLAYER_URL),
                    new MomentSegment(' '),
                    new MomentSegment('scored 52 points against the Celtics', self::BOX_URL),
                ],
            ],
            'triple double' => [
                $linked(MomentKind::TripleDouble, ['points' => 21, 'rebounds' => 12, 'assists' => 10]),
                [
                    new MomentSegment('Test Player (Hawks)', self::PLAYER_URL),
                    new MomentSegment(' '),
                    new MomentSegment('posted a triple-double against the Celtics: 21 pts, 12 reb, 10 ast', self::BOX_URL),
                ],
            ],
            'record game' => [
                $linked(MomentKind::RecordGame, ['value' => 17], 'steals'),
                [
                    new MomentSegment('Test Player (Hawks)', self::PLAYER_URL),
                    new MomentSegment(' '),
                    new MomentSegment('set the league single-game steals record with 17 against the Celtics', self::BOX_URL),
                ],
            ],
            'series clincher' => [
                $linked(MomentKind::SeriesClincher, ['round' => 3, 'wins' => 4, 'losses' => 2], null, 0),
                [
                    new MomentSegment('The Hawks closed out the Celtics 4-2 in the conference finals', self::BOX_URL),
                ],
            ],
            'trade' => [
                $linked(MomentKind::Trade, ['players_moved' => 1, 'mover_pid' => 7], null, 0),
                [
                    new MomentSegment('Test Player', self::PLAYER_URL),
                    new MomentSegment(' '),
                    new MomentSegment('moved from the Celtics to the Hawks', self::BOX_URL),
                ],
            ],
        ];
    }

    /**
     * @param list<MomentSegment> $expected
     */
    #[DataProvider('segmentKindProvider')]
    public function testSegmentsPerKindCarryExactUrls(Moment $moment, array $expected): void
    {
        $this->assertEquals($expected, $this->formatter->segments($moment));
    }

    /**
     * @param list<MomentSegment> $expected
     */
    #[DataProvider('segmentKindProvider')]
    public function testSegmentsJoinToDescribeText(Moment $moment, array $expected): void
    {
        $joined = implode('', array_map(static fn (MomentSegment $s): string => $s->text, $this->formatter->segments($moment)));

        $this->assertSame($this->formatter->describe($moment), $joined);
        $this->assertSame(implode('', array_map(static fn (MomentSegment $s): string => $s->text, $expected)), $joined);
    }

    /**
     * @param list<MomentSegment> $expected
     */
    #[DataProvider('segmentKindProvider')]
    public function testNoBoxScoreUrlWhenOrdinalUnknown(Moment $moment, array $expected): void
    {
        $unknown = $moment->withGameOfThatDay(0);

        $this->assertCount(count($expected), $this->formatter->segments($unknown));
        foreach ($this->formatter->segments($unknown) as $segment) {
            $this->assertTrue(
                $segment->url === null || $segment->url === self::PLAYER_URL,
                'Unexpected url: ' . (string) $segment->url,
            );
        }
    }

    public function testTradeWithoutMoverPidHasNoPlayerUrl(): void
    {
        $moment = self::makeMoment(MomentKind::Trade, ['players_moved' => 1], gameOfThatDay: 3, pid: 0, date: '1999-03-04');

        $this->assertEquals([
            new MomentSegment('Test Player'),
            new MomentSegment(' '),
            new MomentSegment('moved from the Celtics to the Hawks', self::BOX_URL),
        ], $this->formatter->segments($moment));
    }

    /**
     * @param list<MomentSegment> $expected
     */
    #[DataProvider('segmentKindProvider')]
    public function testSeparatorSegmentsAreNeverLinked(Moment $moment, array $expected): void
    {
        $segments = $this->formatter->segments($moment);

        $this->assertCount(count($expected), $segments);
        foreach ($segments as $segment) {
            if ($segment->url !== null) {
                $this->assertSame(trim($segment->text), $segment->text);
            }
            if (trim($segment->text) === '') {
                $this->assertNull($segment->url);
            }
        }
    }

    private static function discordMoment(string $playerName, int $gameOfThatDay = 3): Moment
    {
        return self::makeMoment(
            MomentKind::FiftyPointGame,
            ['points' => 52],
            gameOfThatDay: $gameOfThatDay,
            pid: 7,
            playerName: $playerName,
            date: '1999-03-04',
        );
    }

    public function testDiscordLineUsesMaskedLinks(): void
    {
        $line = $this->formatter->discordLine(self::discordMoment('Test Player'), 2);

        $this->assertSame(
            'On this day in IBL history (2 seasons ago, Mar 4, 1999): '
            . '[Test Player (Hawks)](<https://www.iblhoops.net/ibl5/modules.php?name=Player&pa=showpage&pid=7>) '
            . '[scored 52 points against the Celtics](<https://www.iblhoops.net/ibl5/modules.php?name=GameBoxscore&date=1999-03-04&game=3&team=1>).',
            $line,
        );
    }

    public function testDiscordLineEscapesMarkdownInLinkText(): void
    {
        $line = $this->formatter->discordLine(self::discordMoment('Shaq_O*Neal [Jr] a\\b'), 2);

        $this->assertStringContainsString('[Shaq\\_O\\*Neal \\[Jr\\] a\\\\b (Hawks)](<', $line);
    }

    public function testDiscordLineGuardsAtNameInsideLink(): void
    {
        $line = $this->formatter->discordLine(self::discordMoment('@everyone'), 2);

        $this->assertStringContainsString("[@\u{200B}everyone (Hawks)](<", $line);
        $this->assertStringNotContainsString('@everyone', $line);
    }

    public function testDiscordLineWithoutOrdinalLinksOnlyPlayer(): void
    {
        $line = $this->formatter->discordLine(self::discordMoment('Test Player', 0), 2);

        $this->assertSame(1, substr_count($line, '](<'));
        $this->assertStringContainsString('[Test Player (Hawks)](<https://www.iblhoops.net/ibl5/modules.php?name=Player&pa=showpage&pid=7>) scored 52 points against the Celtics.', $line);
        $this->assertStringNotContainsString('GameBoxscore', $line);
    }

    public function testDiscordLineFallsBackToPlainTextWhenLinksExceedCap(): void
    {
        $line = $this->formatter->discordLine(self::discordMoment(str_repeat('A', 1880)), 2);

        $this->assertStringNotContainsString('](<', $line);
        $this->assertLessThanOrEqual(1900, mb_strlen($line));
        $this->assertStringStartsWith('On this day in IBL history (2 seasons ago, Mar 4, 1999): ', $line);
    }

    public function testDiscordLineKeepsLinksAtExactCap(): void
    {
        $base = mb_strlen($this->formatter->discordLine(self::discordMoment('A'), 2));
        $name = str_repeat('A', 1900 - $base + 1);

        $line = $this->formatter->discordLine(self::discordMoment($name), 2);

        $this->assertSame(1900, mb_strlen($line));
        $this->assertStringContainsString('](<', $line);

        $over = $this->formatter->discordLine(self::discordMoment($name . 'A'), 2);
        $this->assertStringNotContainsString('](<', $over);
    }
}
