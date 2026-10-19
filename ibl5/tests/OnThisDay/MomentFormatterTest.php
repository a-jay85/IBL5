<?php

declare(strict_types=1);

namespace Tests\OnThisDay;

use OnThisDay\Moment;
use OnThisDay\MomentFormatter;
use OnThisDay\MomentKind;
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
    ): Moment {
        return new Moment(
            kind: $kind,
            key: $kind->value . ':2019-02-14:1',
            date: '2019-02-14',
            seasonYear: 2019,
            pid: 1,
            playerName: 'Test Player',
            teamId: 1,
            teamName: 'Hawks',
            opponentTeamId: 2,
            opponentName: 'Celtics',
            facts: $facts,
            statLabel: $statLabel,
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

    public function testDateLabelFormatsMonthDayYear(): void
    {
        $this->assertSame('Feb 14, 2019', $this->formatter->dateLabel('2019-02-14'));
    }
}
