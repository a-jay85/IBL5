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

    // ── Discord message tests ──────────────────────────────────────────────

    public function testDiscordMessageIsEmptyForNoRejects(): void
    {
        $summary = RejectSummary::fromRejects([]);

        self::assertSame('', $summary->discordMessage());
    }

    public function testDiscordMessageContainsCountSpanTriplesAndSource(): void
    {
        $rejects = [];
        for ($i = 0; $i < 621; $i++) {
            $rejects[] = new RejectedGame(
                gameDate: $i < 310 ? '2008-04-05' : '2008-06-25',
                visitorTeamid: 21,
                homeTeamid: 17,
                gameOfThatDay: $i + 1,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            );
        }

        $summary = RejectSummary::fromRejects($rejects, '07-08_36_playoffs.zip');
        $msg = $summary->discordMessage();

        self::assertStringContainsString('621', $msg);
        self::assertStringContainsString('2008-04-05', $msg);
        self::assertStringContainsString('2008-06-25', $msg);
        self::assertStringContainsString('07-08_36_playoffs.zip', $msg);
        // At least one triple line must appear
        self::assertStringContainsString('21@17', $msg);
    }

    public function testDiscordMessageOmitsSourceLineWhenArchiveIsNull(): void
    {
        $summary = RejectSummary::fromRejects([$this->makeReject('2008-04-05')], null);
        $msg = $summary->discordMessage();

        self::assertStringNotContainsString('Source:', $msg);
        self::assertStringNotContainsString('unknown', $msg);
    }

    public function testDiscordMessageCollapsesSpanForSingleDate(): void
    {
        $rejects = [
            $this->makeReject('2008-04-05'),
            $this->makeReject('2008-04-05'),
        ];

        $summary = RejectSummary::fromRejects($rejects);
        $msg = $summary->discordMessage();

        self::assertStringContainsString('Dates: 2008-04-05', $msg);
        self::assertStringNotContainsString('through', $msg);
    }

    public function testDiscordMessageCapsTriplesAtTenWithOverflowLine(): void
    {
        $rejects = [];
        for ($i = 0; $i < 30; $i++) {
            $rejects[] = new RejectedGame(
                gameDate: '2008-04-05',
                visitorTeamid: $i + 1,
                homeTeamid: $i + 2,
                gameOfThatDay: $i + 1,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            );
        }

        $summary = RejectSummary::fromRejects($rejects);
        $msg = $summary->discordMessage();

        // Count indented triple lines
        $tripleLines = array_filter(
            explode("\n", $msg),
            static fn (string $line): bool => str_starts_with($line, '  '),
        );
        self::assertCount(10, $tripleLines);
        self::assertStringContainsString('... and 20 more.', $msg);

        // Boundary: exactly 10 rejects — no overflow line
        $exactRejects = array_slice($rejects, 0, 10);
        $exactSummary = RejectSummary::fromRejects($exactRejects);
        $exactMsg = $exactSummary->discordMessage();
        self::assertStringNotContainsString('... and', $exactMsg);
    }

    public function testDiscordMessageStaysUnderDiscordCharLimit(): void
    {
        // Use a very long source archive name to push total length over 1900 chars.
        $longArchive = str_repeat('x', 2000);
        $rejects = [];
        for ($i = 0; $i < 20; $i++) {
            $rejects[] = $this->makeReject('2008-04-05');
        }

        $summary = RejectSummary::fromRejects($rejects, $longArchive);
        $msg = $summary->discordMessage();

        self::assertLessThanOrEqual(RejectSummary::DISCORD_MAX_CHARS, mb_strlen($msg));
        self::assertStringEndsWith('(truncated)', $msg);
    }

    public function testDiscordMessageRemainsValidUtf8AfterTruncation(): void
    {
        // Fill the source archive entirely with multibyte chars (é = 2 UTF-8 bytes, 1 mb_strlen char).
        // This guarantees that wherever mb_substr cuts, it lands on a character boundary.
        // Using strlen/substr instead would split an é producing invalid UTF-8.
        $longArchive = str_repeat('é', 1200);
        $rejects = [];
        for ($i = 0; $i < 20; $i++) {
            $rejects[] = $this->makeReject('2008-04-05');
        }

        $summary = RejectSummary::fromRejects($rejects, $longArchive);
        $msg = $summary->discordMessage();

        self::assertTrue(mb_check_encoding($msg, 'UTF-8'), 'Discord message must be valid UTF-8 after truncation');
        self::assertNotFalse(json_encode(['content' => $msg]), 'json_encode must not return false on a truncated message');
    }

    // ── auditNote() ───────────────────────────────────────────────────────────

    public function testAuditNoteIsEmptyWhenRecordedCountIsNull(): void
    {
        $summary = RejectSummary::fromRejects([$this->makeReject('2008-04-05')], 'archive.zip', null);
        self::assertSame('', $summary->auditNote());
    }

    public function testAuditNoteIsEmptyWhenThereAreNoRejects(): void
    {
        $summary = RejectSummary::fromRejects([], null, 0);
        self::assertSame('', $summary->auditNote());
    }

    public function testAuditNoteReportsAuditWriteFailedWhenRecordedCountIsZero(): void
    {
        $summary = RejectSummary::fromRejects([$this->makeReject('2008-04-05')], null, 0);
        $note = $summary->auditNote();
        self::assertStringContainsString('AUDIT WRITE FAILED', $note);
        self::assertStringContainsString('still blocked from import', $note);
        self::assertStringContainsString('1', $note);
    }

    public function testAuditNoteReportsPartialRecordWhenRecordedCountIsLessThanCount(): void
    {
        $rejects = [$this->makeReject('2008-04-05'), $this->makeReject('2008-04-06')];
        $summary = RejectSummary::fromRejects($rejects, null, 1);
        $note = $summary->auditNote();
        self::assertStringContainsString('cap reached', $note);
        self::assertStringContainsString('1 of 2', $note);
    }

    public function testAuditNoteReportsFullRecordWhenRecordedCountEqualsCount(): void
    {
        $rejects = [$this->makeReject('2008-04-05'), $this->makeReject('2008-04-06')];
        $summary = RejectSummary::fromRejects($rejects, null, 2);
        $note = $summary->auditNote();
        self::assertStringContainsString('2 of 2', $note);
        self::assertStringNotContainsString('cap reached', $note);
        self::assertStringNotContainsString('AUDIT WRITE FAILED', $note);
    }

    // ── discordMessage() with recordedCount ───────────────────────────────────

    public function testDiscordMessageEmbeddsAuditNoteWhenRecordedCountIsZero(): void
    {
        $summary = RejectSummary::fromRejects([$this->makeReject('2008-04-05')], 'archive.zip', 0);
        $msg = $summary->discordMessage();
        self::assertStringContainsString('AUDIT WRITE FAILED', $msg);
        // Audit note appears between Reasons and Rejected
        $reasonsPos = strpos($msg, 'Reasons:');
        $auditPos   = strpos($msg, 'AUDIT WRITE FAILED');
        $rejectedPos = strpos($msg, 'Rejected:');
        self::assertNotFalse($reasonsPos);
        self::assertNotFalse($auditPos);
        self::assertNotFalse($rejectedPos);
        self::assertGreaterThan($reasonsPos, $auditPos);
        self::assertGreaterThan($auditPos, $rejectedPos);
    }

    public function testDiscordMessageHasNoAuditNoteWhenRecordedCountIsNull(): void
    {
        $summary = RejectSummary::fromRejects([$this->makeReject('2008-04-05')], 'archive.zip');
        $msg = $summary->discordMessage();
        self::assertStringNotContainsString('AUDIT WRITE FAILED', $msg);
        self::assertStringNotContainsString('Recorded', $msg);
    }

    public function testDiscordMessageTruncationStillWorksWithAuditNote(): void
    {
        // A large number of rejects pushes the message over 1900 chars; audit note must not break truncation.
        $rejects = [];
        for ($i = 0; $i < 50; $i++) {
            $rejects[] = $this->makeReject('2008-04-05');
        }
        $summary = RejectSummary::fromRejects($rejects, str_repeat('x', 200), 50);
        $msg = $summary->discordMessage();
        self::assertLessThanOrEqual(RejectSummary::DISCORD_MAX_CHARS, mb_strlen($msg));
        self::assertTrue(mb_check_encoding($msg, 'UTF-8'));
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
