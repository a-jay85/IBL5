<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\FreeAgency;

use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;

use FreeAgency\Contracts\FreeAgencyDiscordDispatcherInterface;
use FreeAgency\Admin\FreeAgencyAdminProcessor;
use FreeAgency\Admin\FreeAgencyAdminRepository;
use League\LeagueContext;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * The delayed-replay proof for the free agency day re-run guard.
 *
 * This is the test the guard exists for. The immediate-replay test in
 * FreeAgencyAdminAssignTest passes even with the fix reverted, because a second
 * UPDATE against an unchanged row reports affected_rows = 0, successCount stays
 * 0, and the news insert is gated out by `$successCount > 0`. That is an
 * accident of MySQL semantics, not a guard.
 *
 * The real-world failure is delayed: a later FA day, an admin correction, or a
 * .plr import moves the signed players back off the team. Now the replayed
 * UPDATEs match rows again, affected_rows goes positive, successCount goes
 * positive, the news condition fires, and a SECOND nuke_stories row is written
 * while markMleUsed() burns the team's MLE a second time.
 *
 * Mutation killed: deleting the insertDayProcessedMarker() call from the
 * transactional() closure in FreeAgencyAdminRepository::executeSigningsTransactionally().
 * With that call gone, this test fails with two nuke_stories rows and has_mle = 0.
 */
#[Group('database')]
class FreeAgencyDayRerunGuardTest extends DatabaseTestCase
{
    private FreeAgencyAdminRepository $repo;
    private FreeAgencyAdminProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new FreeAgencyAdminRepository($this->db, new LeagueContext());

        $noopDispatcher = new class implements FreeAgencyDiscordDispatcherInterface {
            public function dispatch(string $message): void
            {
            }
        };

        $this->processor = new FreeAgencyAdminProcessor(
            $this->repo,
            $this->db,
            new NullLogger(),
            $noopDispatcher
        );
    }

    public function testDelayedReplayOfAnAlreadyProcessedDayWritesNothing(): void
    {
        $day = 7;
        $pid = 900020;
        $teamId = 1;
        $teamName = 'Metros';
        $newsTitle = '2006 IBL Free Agency, Days 7-8';

        $this->insertTestPlayer($pid, 'FA Delayed Replay Player', [
            'teamid' => 0,
            'cy' => 5,
            'cyt' => 0,
            'salary_yr1' => 0,
            'fa_signing_flag' => 0,
        ]);
        $this->setHasMle($teamName, 1);

        $signings = [
            [
                'playerId' => $pid,
                'teamId' => $teamId,
                'offerYears' => 3,
                'offers' => [
                    'offer1' => 1500,
                    'offer2' => 1600,
                    'offer3' => 1700,
                    'offer4' => 0,
                    'offer5' => 0,
                    'offer6' => 0,
                ],
                'usedMle' => true,
                'usedLle' => false,
                'teamName' => $teamName,
            ],
        ];

        // 1. Execute the day for real.
        $first = $this->processor->executeSignings(
            $day,
            $signings,
            $newsTitle,
            'Player accepted a deal.',
            'Full body of the story.'
        );
        self::assertTrue($first['success'], 'the first run must succeed');

        // 2. Capture the post-run state.
        $afterFirst = $this->readPlayer($pid);
        self::assertSame($teamId, $afterFirst['teamid']);
        self::assertSame(1, $afterFirst['fa_signing_flag']);
        self::assertSame(0, $this->readHasMle($teamName), 'the first run consumes the MLE');
        self::assertSame(1, $this->countStories($newsTitle));

        // 3. Move the player back off the team, exactly as a later FA day, an admin
        //    correction, or a .plr import does in production. THIS is what makes the
        //    replayed UPDATEs match rows again — without it the replay is a silent
        //    no-op and proves nothing.
        $this->resetPlayerToFreeAgency($pid);
        $this->setHasMle($teamName, 1);

        // 4. Replay the identical payload.
        $second = $this->processor->executeSignings(
            $day,
            $signings,
            $newsTitle,
            'Player accepted a deal.',
            'Full body of the story.'
        );

        // 5. Nothing moved.
        self::assertFalse($second['success'], 'the replay must be refused');
        self::assertStringContainsString('already been processed', $second['message']);

        self::assertSame(
            1,
            $this->countMarkers(LeagueContext::LEAGUE_IBL, $this->currentSeasonEndingYear(), $day),
            'exactly one marker row for the (league, season, day) triple'
        );
        self::assertSame(
            1,
            $this->countStories($newsTitle),
            'a second news story here is the exact production bug this guard prevents'
        );
        self::assertSame(
            1,
            $this->readHasMle($teamName),
            'markMleUsed() must not have run a second time'
        );

        $afterReplay = $this->readPlayer($pid);
        self::assertSame(0, $afterReplay['teamid'], 'the replay must not re-sign the player');
        self::assertSame(0, $afterReplay['fa_signing_flag']);
    }

    // ---- helpers -------------------------------------------------------------

    /**
     * @return array{teamid: int, fa_signing_flag: int}
     */
    private function readPlayer(int $pid): array
    {
        $stmt = $this->db->prepare('SELECT teamid, fa_signing_flag FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);

        return [
            'teamid' => (int) $row['teamid'],
            'fa_signing_flag' => (int) $row['fa_signing_flag'],
        ];
    }

    private function resetPlayerToFreeAgency(int $pid): void
    {
        $stmt = $this->db->prepare(
            'UPDATE ibl_plr SET teamid = 0, fa_signing_flag = 0, cy = 5, cyt = 0 WHERE pid = ? LIMIT 1'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $stmt->close();
    }

    private function setHasMle(string $teamName, int $hasMle): void
    {
        $stmt = $this->db->prepare('UPDATE ibl_team_info SET has_mle = ? WHERE team_name = ? LIMIT 1');
        self::assertNotFalse($stmt);
        $stmt->bind_param('is', $hasMle, $teamName);
        $stmt->execute();
        $stmt->close();
    }

    private function readHasMle(string $teamName): int
    {
        $stmt = $this->db->prepare('SELECT has_mle FROM ibl_team_info WHERE team_name = ? LIMIT 1');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $teamName);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row, "team '{$teamName}' must exist in ibl_team_info");

        return (int) $row['has_mle'];
    }

    private function currentSeasonEndingYear(): int
    {
        $key = 'Current Season Ending Year';
        $league = LeagueContext::LEAGUE_IBL;
        $stmt = $this->db->prepare('SELECT value FROM ibl_settings WHERE setting_key = ? AND league = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('ss', $key, $league);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row, "the test database must carry a '{$key}' row for '{$league}'");

        return (int) $row['value'];
    }

    private function countMarkers(string $league, int $year, int $day): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS c FROM ibl_fa_days_processed
             WHERE league = ? AND season_ending_year = ? AND day = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('sii', $league, $year, $day);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['c'] ?? 0);
    }

    private function countStories(string $title): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS c FROM nuke_stories WHERE title = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $title);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['c'] ?? 0);
    }
}
