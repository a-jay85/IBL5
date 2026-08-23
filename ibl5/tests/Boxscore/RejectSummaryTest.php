<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\RejectedGame;
use Boxscore\RejectSummary;
use PHPUnit\Framework\TestCase;

final class RejectSummaryTest extends TestCase
{
    public function testFromRejectsOnEmptyListIsEmptyWithNullDates(): void
    {
        $summary = RejectSummary::fromRejects([]);

        self::assertSame(0, $summary->count);
        self::assertNull($summary->firstDate);
        self::assertNull($summary->lastDate);
        self::assertTrue($summary->isEmpty());
        self::assertSame('', $summary->headline());
        self::assertSame([], $summary->triples());
    }

    public function testHeadlineUsesSingleDateWhenSpanIsOneDay(): void
    {
        $rejects = [
            $this->makeReject('2008-04-05'),
            $this->makeReject('2008-04-05'),
            $this->makeReject('2008-04-05'),
        ];

        $summary = RejectSummary::fromRejects($rejects);

        self::assertStringContainsString('for 2008-04-05', $summary->headline());
        self::assertStringNotContainsString('through', $summary->headline());
        self::assertStringContainsString('3 game(s) rejected', $summary->headline());
    }

    public function testHeadlineUsesSpanWhenDatesDiffer(): void
    {
        // Supplied in descending date order to guard a naive "first element is min" fold.
        $rejects = [
            $this->makeReject('2008-06-25'),
            $this->makeReject('2008-04-05'),
        ];

        $summary = RejectSummary::fromRejects($rejects);

        self::assertStringContainsString('for 2008-04-05 through 2008-06-25', $summary->headline());
        self::assertSame('2008-04-05', $summary->firstDate);
        self::assertSame('2008-06-25', $summary->lastDate);
    }

    public function testHeadlineOmitsSourceClauseWhenArchiveIsNull(): void
    {
        $summary = RejectSummary::fromRejects([$this->makeReject('2008-04-05')], null);

        self::assertStringNotContainsString('source:', $summary->headline());
        self::assertStringNotContainsString('unknown', $summary->headline());
    }

    public function testHeadlineIncludesSourceArchiveWhenProvided(): void
    {
        $summary = RejectSummary::fromRejects(
            [$this->makeReject('2008-04-05')],
            '07-08_36_playoffs.zip'
        );

        self::assertStringContainsString('(source: 07-08_36_playoffs.zip)', $summary->headline());
    }

    public function testTriplesAreCappedAndOverflowCounted(): void
    {
        $rejects = [];
        for ($i = 0; $i < 30; $i++) {
            $rejects[] = new RejectedGame(
                gameDate: '2008-04-05',
                visitorTeamid: $i + 1,
                homeTeamid: $i + 2,
                gameOfThatDay: 1,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            );
        }

        $summary = RejectSummary::fromRejects($rejects);

        self::assertCount(25, $summary->triples());
        self::assertSame(5, $summary->overflowCount());

        // Build a summary with exactly the limit — overflow is 0
        $exactRejects = array_slice($rejects, 0, 25);
        $exactSummary = RejectSummary::fromRejects($exactRejects);
        self::assertSame(0, $exactSummary->overflowCount());
    }

    public function testReasonCountsTallyPerReason(): void
    {
        $rejects = [];
        // 3 not_in_schedule
        for ($i = 0; $i < 3; $i++) {
            $rejects[] = new RejectedGame(
                gameDate: '2008-04-05',
                visitorTeamid: $i + 1,
                homeTeamid: $i + 10,
                gameOfThatDay: 1,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            );
        }
        // 2 duplicate_triple
        for ($i = 0; $i < 2; $i++) {
            $rejects[] = new RejectedGame(
                gameDate: '2008-04-05',
                visitorTeamid: $i + 20,
                homeTeamid: $i + 30,
                gameOfThatDay: 2,
                reason: RejectedGame::REASON_DUPLICATE_TRIPLE,
                storedGameOfThatDay: [1],
            );
        }

        $summary = RejectSummary::fromRejects($rejects);

        self::assertSame(3, $summary->reasonCounts[RejectedGame::REASON_NOT_IN_SCHEDULE]);
        self::assertSame(2, $summary->reasonCounts[RejectedGame::REASON_DUPLICATE_TRIPLE]);
        self::assertSame(5, $summary->count);
    }

    private function makeReject(string $gameDate): RejectedGame
    {
        return new RejectedGame(
            gameDate: $gameDate,
            visitorTeamid: 1,
            homeTeamid: 2,
            gameOfThatDay: 1,
            reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
        );
    }
}
