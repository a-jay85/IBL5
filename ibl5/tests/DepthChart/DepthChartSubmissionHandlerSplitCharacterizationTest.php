<?php

declare(strict_types=1);

namespace Tests\DepthChart;

use DepthChart\DepthChartSubmissionHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\Mocks\Season;
use Tests\WideUnit\Mocks\TestDataFactory;
use Tests\WideUnit\WideUnitTestCase;

/**
 * Pre-split characterization of DepthChartSubmissionHandler::handleSubmission().
 *
 * Pins the exact result arrays, the read-before-write ordering, and the audit
 * log contract of every early-return and success path, so a later split of the
 * method is provably behavior-preserving. Every test passes against the
 * unsplit handler.
 *
 * @covers \DepthChart\DepthChartSubmissionHandler
 */
class DepthChartSubmissionHandlerSplitCharacterizationTest extends WideUnitTestCase
{
    private const TEAMID = 7;
    /** Stripped to '' by saveDepthChartFile()'s safe-name guard: no file write, no mail. */
    private const SIDE_EFFECT_FREE_TEAM = '***';
    private const MISSING_TEAM_HTML = '<strong class="ibl-form-error">Error: Missing required team information.</strong>';

    /** @return list<array<string, mixed>> */
    private function roster(int $count): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = TestDataFactory::createPlayer(['pid' => 100 + $i, 'name' => "Roster P$i", 'teamid' => self::TEAMID]);
        }
        return $rows;
    }

    /**
     * Regular-season-valid POST: exactly 12 active, 3 non-injured deep at every position,
     * each player a starter (depth 1) at most once. Players 1-3 fill pg, 4-6 sg, 7-9 sf,
     * 10-12 pf, 13-15 c; a 16th+ player is bench pg (depth 4) and inactive.
     * @param list<array<string, mixed>> $roster
     * @return array<string, string>
     */
    private function validPost(array $roster): array
    {
        $positions = ['pg', 'sg', 'sf', 'pf', 'c'];
        $post = [];
        foreach (array_values($roster) as $idx => $row) {
            $n = $idx + 1;
            $post["Name$n"] = (string) $row['name'];
            $post["pid$n"] = (string) $row['pid'];
            foreach ($positions as $p) {
                $post["$p$n"] = '0';
            }
            if ($idx < 15) {
                $post[$positions[intdiv($idx, 3)] . $n] = (string) (($idx % 3) + 1);
            } else {
                $post["pg$n"] = '4';
            }
            $post["canPlayInGame$n"] = $n <= 12 ? '1' : '0';
            $post["min$n"] = '20';
            $post["Injury$n"] = '0';
        }
        return $post;
    }

    /** @param list<array<string, mixed>> $rosterRows */
    private function buildHandler(
        array $rosterRows,
        ?string $phase = null,
        ?int $teamid = self::TEAMID,
        ?string $sessionTeam = self::SIDE_EFFECT_FREE_TEAM,
        ?\Psr\Log\LoggerInterface $auditLogger = null
    ): DepthChartSubmissionHandler {
        $commonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTeamnameFromUsername')->willReturn($sessionTeam);
        $commonRepo->method('getTidFromTeamname')->willReturn($teamid);
        $this->mockDb->onQuery('FROM ibl_plr WHERE teamid', $rosterRows);
        $season = new Season($this->mockDb);           // Tests\WideUnit\Mocks\Season
        if ($phase !== null) {
            $season->phase = $phase;
        }
        return new DepthChartSubmissionHandler(
            $this->mockDb,
            $commonRepo,
            $auditLogger ?? new NullLogger(),
            new NullLogger(),
            $season
        );
    }

    /** @return iterable<string, array{0: string|null}> */
    public static function unauthorizedSessionTeamProvider(): iterable
    {
        yield 'null team' => [null];
        yield 'empty team' => [''];
        yield 'free agents team' => [\League\League::FREE_AGENTS_TEAM_NAME];
    }

    /** @return iterable<string, array{0: string}> */
    public static function strippedToEmptyProvider(): iterable
    {
        yield 'empty tag only' => ['<b></b>'];
        yield 'spaces only' => ['   '];
        yield 'tab and newline only' => ["\t\n"];
    }

    #[DataProvider('unauthorizedSessionTeamProvider')]
    public function testRejectsUnauthorizedSessionTeamWithExactResultAndNoWrite(?string $sessionTeam): void
    {
        $commonRepo = self::createMock(TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTeamnameFromUsername')->willReturn($sessionTeam);
        $commonRepo->expects($this->never())->method('getTidFromTeamname');
        $handler = new DepthChartSubmissionHandler(
            $this->mockDb,
            $commonRepo,
            new NullLogger(),
            new NullLogger(),
            new Season($this->mockDb)
        );

        $post = ['pid1' => '101', 'Name1' => 'x'];
        $result = $handler->handleSubmission($post, 'testuser');

        $this->assertSame(
            ['success' => false, 'fileOk' => false, 'errorsHtml' => self::MISSING_TEAM_HTML, 'postData' => $post],
            $result
        );
        $this->assertSame([], $this->getExecutedQueries());
    }

    #[DataProvider('strippedToEmptyProvider')]
    public function testRejectsSessionTeamThatStripsToEmptyWithNoTeamidLookup(string $sessionTeam): void
    {
        $commonRepo = self::createMock(TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTeamnameFromUsername')->willReturn($sessionTeam);
        $commonRepo->expects($this->never())->method('getTidFromTeamname');
        $handler = new DepthChartSubmissionHandler(
            $this->mockDb,
            $commonRepo,
            new NullLogger(),
            new NullLogger(),
            new Season($this->mockDb)
        );

        $post = ['pid1' => '101', 'Name1' => 'x'];
        $result = $handler->handleSubmission($post, 'testuser');

        $this->assertSame(
            ['success' => false, 'fileOk' => false, 'errorsHtml' => self::MISSING_TEAM_HTML, 'postData' => $post],
            $result
        );
        $this->assertSame([], $this->getExecutedQueries());
    }

    public function testRejectsUnresolvableTeamidWithExactResultAndNoRosterRead(): void
    {
        $roster = $this->roster(15);
        $post = $this->validPost($roster);
        $handler = $this->buildHandler($roster, null, null);

        $result = $handler->handleSubmission($post, 'testuser');

        $this->assertSame(
            ['success' => false, 'fileOk' => false, 'errorsHtml' => self::MISSING_TEAM_HTML, 'postData' => $post],
            $result
        );
        $this->assertSame([], $this->getExecutedQueries());
    }

    public function testRosterRejectionRendersValidatorErrorsAndWritesNothing(): void
    {
        $roster = $this->roster(15);
        $post = $this->validPost($roster);
        $post['pid15'] = '999';

        $audit = new class extends \Psr\Log\AbstractLogger {
            /** @var list<array{0: string, 1: string, 2: array<string, mixed>}> */
            public array $records = [];
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };
        $handler = $this->buildHandler($roster, null, self::TEAMID, self::SIDE_EFFECT_FREE_TEAM, $audit);

        $result = $handler->handleSubmission($post, 'testuser');

        $processed = (new \DepthChart\DepthChartProcessor())->processSubmission($post, 15);
        $expected = \DepthChart\DepthChartErrorHtmlRenderer::render(
            (new \DepthChart\DepthChartValidator())->validateRoster(
                array_column($processed['playerData'], 'pid'),
                array_column($roster, 'pid')
            )->getErrors()
        );
        $this->assertSame(
            ['success' => false, 'fileOk' => false, 'errorsHtml' => $expected, 'postData' => $post],
            $result
        );
        $this->assertQueryExecuted('FROM ibl_plr');
        $this->assertQueryNotExecuted('UPDATE ibl_plr');
        $this->assertQueryNotExecuted('UPDATE ibl_team_info');
        $this->assertQueryNotExecuted('INSERT');
        $this->assertSame([], $audit->records);
    }

    public function testPhaseValidationRejectionRendersValidatorErrorsAndWritesNothing(): void
    {
        $roster = $this->roster(15);
        $post = $this->validPost($roster);
        for ($n = 1; $n <= 15; $n++) {
            $post["canPlayInGame$n"] = '0';
        }

        $audit = new class extends \Psr\Log\AbstractLogger {
            /** @var list<array{0: string, 1: string, 2: array<string, mixed>}> */
            public array $records = [];
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };
        $handler = $this->buildHandler($roster, null, self::TEAMID, self::SIDE_EFFECT_FREE_TEAM, $audit);

        $result = $handler->handleSubmission($post, 'testuser');

        $processed = (new \DepthChart\DepthChartProcessor())->processSubmission($post, 15);
        $expected = \DepthChart\DepthChartErrorHtmlRenderer::render(
            (new \DepthChart\DepthChartValidator())->validate($processed, 'Regular Season')->getErrors()
        );
        $this->assertSame(
            ['success' => false, 'fileOk' => false, 'errorsHtml' => $expected, 'postData' => $post],
            $result
        );
        $this->assertStringContainsString('active players', $result['errorsHtml']);
        $this->assertQueryExecuted('FROM ibl_plr');
        $this->assertQueryNotExecuted('UPDATE ibl_plr');
        $this->assertQueryNotExecuted('UPDATE ibl_team_info');
        $this->assertQueryNotExecuted('INSERT');
        $this->assertSame([], $audit->records);
    }

    /**
     * The stubbed TeamIdentityRepositoryInterface returns no username, so
     * saveDepthChartSnapshot() returns early and logs no snapshot query; only
     * the read-before-write and player-before-team_info orderings are pinned.
     */
    public function testSuccessWritesAfterRosterReadAndLogsAuditOnce(): void
    {
        $roster = $this->roster(15);

        $audit = new class extends \Psr\Log\AbstractLogger {
            /** @var list<array{0: string, 1: string, 2: array<string, mixed>}> */
            public array $records = [];
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };
        $handler = $this->buildHandler($roster, null, self::TEAMID, self::SIDE_EFFECT_FREE_TEAM, $audit);

        $result = $handler->handleSubmission($this->validPost($roster), 'testuser');

        $this->assertSame(['success', 'fileOk', 'errorsHtml', 'postData'], array_keys($result));
        $this->assertTrue($result['success']);
        $this->assertSame('', $result['errorsHtml']);
        $this->assertSame([], $result['postData']);
        $this->assertIsBool($result['fileOk']);

        $q = $this->getExecutedQueries();
        $read = null;
        $firstPlr = null;
        $teamInfo = null;
        foreach ($q as $idx => $sql) {
            if (str_contains($sql, 'FROM ibl_plr')) {
                $read = $idx;
            }
            if ($firstPlr === null && str_contains($sql, 'UPDATE ibl_plr')) {
                $firstPlr = $idx;
            }
            if ($teamInfo === null && str_contains($sql, 'UPDATE ibl_team_info')) {
                $teamInfo = $idx;
            }
        }
        $this->assertNotNull($read);
        $this->assertNotNull($firstPlr);
        $this->assertNotNull($teamInfo);
        $this->assertLessThan($firstPlr, $read);
        $this->assertLessThan($teamInfo, $firstPlr);
        $this->assertCount(
            15,
            array_filter($q, static fn (string $s): bool => str_contains($s, 'UPDATE ibl_plr'))
        );

        $this->assertCount(1, $audit->records);
        $this->assertSame(
            ['info', 'depth_chart_submitted', ['action' => 'depth_chart_submitted', 'team_name' => '***']],
            $audit->records[0]
        );
    }

    public function testNoAuditLogOnAnyRejectionPath(): void
    {
        $audit = new class extends \Psr\Log\AbstractLogger {
            /** @var list<array{0: string, 1: string, 2: array<string, mixed>}> */
            public array $records = [];
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };
        $roster = $this->roster(15);

        $unauthorized = $this->buildHandler($roster, null, self::TEAMID, null, $audit);
        $result = $unauthorized->handleSubmission($this->validPost($roster), 'testuser');
        $this->assertFalse($result['success']);
        $this->assertSame([], $audit->records);

        $unresolvable = $this->buildHandler($roster, null, null, self::SIDE_EFFECT_FREE_TEAM, $audit);
        $result = $unresolvable->handleSubmission($this->validPost($roster), 'testuser');
        $this->assertFalse($result['success']);
        $this->assertSame([], $audit->records);

        $post = $this->validPost($roster);
        $post['pid15'] = '999';
        $rosterReject = $this->buildHandler($roster, null, self::TEAMID, self::SIDE_EFFECT_FREE_TEAM, $audit);
        $result = $rosterReject->handleSubmission($post, 'testuser');
        $this->assertFalse($result['success']);
        $this->assertSame([], $audit->records);
    }
}
