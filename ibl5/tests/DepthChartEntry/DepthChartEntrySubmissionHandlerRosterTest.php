<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use Tests\WideUnit\WideUnitTestCase;
use DepthChartEntry\DepthChartEntrySubmissionHandler;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\Mocks\TestDataFactory;
use Tests\WideUnit\Mocks\Season;
use Psr\Log\NullLogger;

/**
 * Roster validation proof for DepthChartEntrySubmissionHandler.
 *
 * Tests that the handler enforces roster membership: only pids on the
 * session team's roster may appear, each exactly once, and every roster
 * pid must appear. Foreign pids, duplicates, and omissions are rejected
 * before any write.
 *
 * @covers \DepthChartEntry\DepthChartEntrySubmissionHandler
 */
class DepthChartEntrySubmissionHandlerRosterTest extends WideUnitTestCase
{
    private const TEAMID = 7;
    /** Stripped to '' by saveDepthChartFile()'s safe-name guard: no file write, no mail. */
    private const SIDE_EFFECT_FREE_TEAM = '***';

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
    private function buildHandler(array $rosterRows, ?string $phase = null, ?int $teamid = self::TEAMID): DepthChartEntrySubmissionHandler
    {
        $commonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTeamnameFromUsername')->willReturn(self::SIDE_EFFECT_FREE_TEAM);
        $commonRepo->method('getTidFromTeamname')->willReturn($teamid);
        $this->mockDb->onQuery('FROM ibl_plr WHERE teamid', $rosterRows);
        $season = new Season($this->mockDb);           // Tests\WideUnit\Mocks\Season
        if ($phase !== null) {
            $season->phase = $phase;
        }
        return new DepthChartEntrySubmissionHandler($this->mockDb, $commonRepo, new NullLogger(), new NullLogger(), $season);
    }

    /** @return list<string> */
    private function playerUpdates(): array
    {
        return array_values(array_filter(
            $this->getExecutedQueries(),
            static fn (string $q): bool => str_contains($q, 'UPDATE ibl_plr')
        ));
    }

    public function testAcceptsValidFullRosterInRegularSeason(): void
    {
        $roster = $this->roster(15);
        $handler = $this->buildHandler($roster);
        $result = $handler->handleSubmission($this->validPost($roster), 'testuser');

        $this->assertTrue($result['success']);
        $this->assertCount(15, $this->playerUpdates());
        $this->assertQueryExecuted('UPDATE ibl_team_info');
    }

    public function testAcceptsValidFullRosterInPlayoffs(): void
    {
        $roster = $this->roster(15);
        $handler = $this->buildHandler($roster, 'Playoffs');
        $result = $handler->handleSubmission($this->validPost($roster), 'testuser');

        $this->assertTrue($result['success']);
        $this->assertCount(15, $this->playerUpdates());
        $this->assertQueryExecuted('UPDATE ibl_team_info');
    }

    public function testRejectsOmittedRosterPlayerNoWrite(): void
    {
        $roster = $this->roster(15);
        $post = $this->validPost($roster);
        // Remove rows 13, 14, 15
        foreach ([13, 14, 15] as $n) {
            unset($post["Name$n"], $post["pid$n"], $post["pg$n"], $post["sg$n"], $post["sf$n"], $post["pf$n"], $post["c$n"], $post["canPlayInGame$n"], $post["min$n"], $post["Injury$n"]);
        }
        $handler = $this->buildHandler($roster);
        $result = $handler->handleSubmission($post, 'testuser');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('missing a roster player', $result['errorsHtml']);
        $this->assertQueryNotExecuted('UPDATE ibl_plr');
        $this->assertQueryNotExecuted('UPDATE ibl_team_info');
    }

    public function testRejectsForeignPidNoWrite(): void
    {
        $roster = $this->roster(15);
        $post = $this->validPost($roster);
        // Row 15 gets a pid not on the roster
        $post['pid15'] = '999';
        $handler = $this->buildHandler($roster);
        $result = $handler->handleSubmission($post, 'testuser');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not on your roster', $result['errorsHtml']);
        $this->assertStringContainsString('999', $result['errorsHtml']);
        $this->assertQueryNotExecuted('UPDATE ibl_plr');
    }

    public function testRejectsDuplicatePidNoWrite(): void
    {
        $roster = $this->roster(15);
        $post = $this->validPost($roster);
        // Row 15 gets the same pid as row 1
        $post['pid15'] = '101';
        $handler = $this->buildHandler($roster);
        $result = $handler->handleSubmission($post, 'testuser');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('more than once', $result['errorsHtml']);
        $this->assertQueryNotExecuted('UPDATE ibl_plr');
    }

    public function testSavesEveryRowForRosterLargerThanFifteen(): void
    {
        $roster = $this->roster(16);
        $handler = $this->buildHandler($roster);
        $result = $handler->handleSubmission($this->validPost($roster), 'testuser');

        $this->assertTrue($result['success']);
        $this->assertCount(16, $this->playerUpdates());
    }

    public function testEveryUpdateIsKeyedBySessionTeamid(): void
    {
        $roster = $this->roster(15);
        $handler = $this->buildHandler($roster);
        $result = $handler->handleSubmission($this->validPost($roster), 'testuser');

        $this->assertTrue($result['success']);
        $updates = $this->playerUpdates();
        foreach ($updates as $q) {
            $this->assertStringContainsString('AND teamid = ', $q);
            $this->assertStringNotContainsString('WHERE name', $q);
        }
        $this->assertCount(15, $updates);
    }

    public function testRejectsUnresolvableTeamidNoWrite(): void
    {
        $roster = $this->roster(15);
        $handler = $this->buildHandler($roster, null, null);
        $result = $handler->handleSubmission($this->validPost($roster), 'testuser');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing required team information', $result['errorsHtml']);
        $this->assertQueryNotExecuted('FROM ibl_plr');
        $this->assertQueryNotExecuted('UPDATE ibl_plr');
    }

    public function testExtraRowBeyondRosterIsIgnoredNotSaved(): void
    {
        $roster = $this->roster(15);
        $post = $this->validPost($roster);
        // Add a 16th row with a foreign pid (beyond roster size)
        $post['Name16'] = 'Extra Player';
        $post['pid16'] = '999';
        $post['pg16'] = '4';
        $post['sg16'] = '0';
        $post['sf16'] = '0';
        $post['pf16'] = '0';
        $post['c16'] = '0';
        $post['canPlayInGame16'] = '0';
        $post['min16'] = '0';
        $post['Injury16'] = '0';
        $handler = $this->buildHandler($roster);
        $result = $handler->handleSubmission($post, 'testuser');

        $this->assertTrue($result['success']);
        $this->assertCount(15, $this->playerUpdates());
    }
}
