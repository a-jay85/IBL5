<?php

declare(strict_types=1);

namespace Tests\OnThisDay;

use OnThisDay\Moment;
use OnThisDay\MomentKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OnThisDay\Moment
 */
final class MomentTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function validRow(): array
    {
        return [
            'kind' => 'record_game',
            'key' => 'record_game:2001-02-03:7:points',
            'date' => '2001-02-03',
            'seasonYear' => 2001,
            'pid' => 7,
            'playerName' => 'Test Player',
            'teamId' => 3,
            'teamName' => 'Home Team',
            'opponentTeamId' => 4,
            'opponentName' => 'Away Team',
            'facts' => ['value' => 61],
            'statLabel' => 'points',
        ];
    }

    public function testToArrayRoundTripsThroughFromArray(): void
    {
        $moment = new Moment(
            kind: MomentKind::SeriesClincher,
            key: 'series_clincher:2002-05-01:3-4',
            date: '2002-05-01',
            seasonYear: 2002,
            pid: 0,
            playerName: '',
            teamId: 3,
            teamName: 'Winners',
            opponentTeamId: 4,
            opponentName: 'Losers',
            facts: ['round' => 2, 'wins' => 4, 'losses' => 3],
        );

        $row = $moment->toArray();

        $this->assertSame('series_clincher', $row['kind']);
        $this->assertNull($row['statLabel']);
        $this->assertEquals($moment, Moment::fromArray($row));
    }

    public function testFromArrayReadsEveryField(): void
    {
        $moment = Moment::fromArray(self::validRow());

        $this->assertSame(MomentKind::RecordGame, $moment->kind);
        $this->assertSame('record_game:2001-02-03:7:points', $moment->key);
        $this->assertSame('2001-02-03', $moment->date);
        $this->assertSame(2001, $moment->seasonYear);
        $this->assertSame(7, $moment->pid);
        $this->assertSame('Test Player', $moment->playerName);
        $this->assertSame(3, $moment->teamId);
        $this->assertSame('Home Team', $moment->teamName);
        $this->assertSame(4, $moment->opponentTeamId);
        $this->assertSame('Away Team', $moment->opponentName);
        $this->assertSame(['value' => 61], $moment->facts);
        $this->assertSame('points', $moment->statLabel);
    }

    public function testFromArrayAcceptsMissingStatLabel(): void
    {
        $row = self::validRow();
        unset($row['statLabel']);

        $this->assertNull(Moment::fromArray($row)->statLabel);
    }

    /**
     * Each malformed field is rejected.
     */
    #[DataProvider('malformedFieldProvider')]
    public function testFromArrayRejectsMalformedField(string $field, mixed $value): void
    {
        $row = self::validRow();
        $row[$field] = $value;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Malformed moment row$/');

        Moment::fromArray($row);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function malformedFieldProvider(): array
    {
        return [
            'kind not string' => ['kind', 1],
            'key not string' => ['key', null],
            'date not string' => ['date', 20010203],
            'seasonYear not int' => ['seasonYear', '2001'],
            'pid not int' => ['pid', '7'],
            'playerName not string' => ['playerName', 7],
            'teamId not int' => ['teamId', '3'],
            'teamName not string' => ['teamName', null],
            'opponentTeamId not int' => ['opponentTeamId', 4.0],
            'opponentName not string' => ['opponentName', false],
            'facts not array' => ['facts', 'value=61'],
            'statLabel not string' => ['statLabel', 5],
        ];
    }

    /**
     * Each malformed facts entry is rejected.
     *
     * @param array<mixed> $facts
     */
    #[DataProvider('malformedFactsProvider')]
    public function testFromArrayRejectsMalformedFacts(array $facts): void
    {
        $row = self::validRow();
        $row['facts'] = $facts;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Malformed moment facts$/');

        Moment::fromArray($row);
    }

    /**
     * @return array<string, array{array<mixed>}>
     */
    public static function malformedFactsProvider(): array
    {
        return [
            'integer key' => [[61]],
            'string value' => [['value' => '61']],
        ];
    }

    public function testFromArrayRejectsUnknownKind(): void
    {
        $row = self::validRow();
        $row['kind'] = 'buzzer_beater';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Unknown moment kind: buzzer_beater$/');

        Moment::fromArray($row);
    }
}
