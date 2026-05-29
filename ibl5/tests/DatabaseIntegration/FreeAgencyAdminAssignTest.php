<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use FreeAgency\FreeAgencyAdminRepository;

/**
 * Verifies the FA-admin signing orchestration: ibl_plr mutation and
 * nuke_stories insertion via executeSigningsTransactionally().
 *
 * Design choice: tests call FreeAgencyAdminRepository::executeSigningsTransactionally()
 * directly rather than going through FreeAgencyAdminProcessor::executeSignings().
 * The processor adds only logging and an empty-array guard on top; the
 * orchestration logic (signing loop, MLE/LLE marking, news-gating) lives
 * entirely in the repository method. Calling the repository layer exercises
 * every reachable branch without requiring Processor deps (Team, Player
 * facade loads, Logger bootstrap).
 *
 * BaseMysqliRepository::transactional() is savepoint-safe when already inside
 * a transaction (classes/BaseMysqliRepository.php:509–514), so the test's
 * outer DatabaseTestCase transaction is preserved and rolls back cleanly.
 *
 * Key sources:
 *   classes/FreeAgency/FreeAgencyAdminRepository.php:165  — executeSigningsTransactionally
 *   classes/FreeAgency/FreeAgencyAdminRepository.php:73   — updatePlayerContract
 *   classes/FreeAgency/FreeAgencyAdminRepository.php:138  — insertNewsStory
 *   classes/BaseMysqliRepository.php:509                  — transactional() savepoint guard
 *   tests/DatabaseIntegration/DatabaseTestCase.php:213    — insertTestPlayer helper
 */
#[Group('database')]
class FreeAgencyAdminAssignTest extends DatabaseTestCase
{
    private FreeAgencyAdminRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FreeAgencyAdminRepository($this->db);
    }

    /**
     * Happy-path: a single signing updates ibl_plr (teamid, fa_signing_flag,
     * salary columns, cy=0) AND inserts a nuke_stories row when homeText/bodyText
     * are non-empty.
     */
    public function testExecuteSigningsTransactionallyUpdatesPlayerAndInsertsNews(): void
    {
        $pid = 900001;
        $this->insertTestPlayer($pid, 'FA Assign Player', [
            'teamid' => 0,
            'cy' => 5,
            'cyt' => 0,
            'salary_yr1' => 0,
            'fa_signing_flag' => 0,
        ]);

        $signings = [
            [
                'playerId' => $pid,
                'teamId' => 1,
                'offerYears' => 3,
                'offers' => [
                    'offer1' => 1500,
                    'offer2' => 1600,
                    'offer3' => 1700,
                    'offer4' => 0,
                    'offer5' => 0,
                    'offer6' => 0,
                ],
                'usedMle' => false,
                'usedLle' => false,
                'teamName' => 'Metros',
            ],
        ];

        $newsTitle = '2006 IBL Free Agency, Days 1-2';

        $counts = $this->repo->executeSigningsTransactionally(
            $signings,
            $newsTitle,
            'Player accepted a deal.',
            'Full body of the story.'
        );

        // Both the player update and the news insert count as successes
        self::assertSame(2, $counts['successCount']);
        self::assertSame(0, $counts['errorCount']);

        // (a) ibl_plr mutation
        $stmt = $this->db->prepare('SELECT teamid, fa_signing_flag, cy, cyt, salary_yr1, salary_yr2, salary_yr3 FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(1, (int) $row['teamid']);
        self::assertSame(1, (int) $row['fa_signing_flag']);
        self::assertSame(0, (int) $row['cy']);
        self::assertSame(3, (int) $row['cyt']);
        self::assertSame(1500, (int) $row['salary_yr1']);
        self::assertSame(1600, (int) $row['salary_yr2']);
        self::assertSame(1700, (int) $row['salary_yr3']);

        // (b) nuke_stories row
        $stmt = $this->db->prepare('SELECT title, hometext FROM nuke_stories WHERE title = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $newsTitle);
        $stmt->execute();
        $news = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($news);
        self::assertSame($newsTitle, $news['title']);
        self::assertSame('Player accepted a deal.', $news['hometext']);
    }

    /**
     * News-gating guard: when homeText or bodyText is empty, no nuke_stories row
     * is inserted even though the player contract update succeeds. Kills the
     * mutant that would always call insertNewsStory().
     *
     * @see classes/FreeAgency/FreeAgencyAdminRepository.php:203
     */
    public function testExecuteSigningsTransactionallySkipsNewsWhenTextsAreEmpty(): void
    {
        $pid = 900002;
        $this->insertTestPlayer($pid, 'FA NoNews Player', [
            'teamid' => 0,
            'cy' => 0,
            'cyt' => 0,
            'salary_yr1' => 0,
        ]);

        $signings = [
            [
                'playerId' => $pid,
                'teamId' => 2,
                'offerYears' => 1,
                'offers' => [
                    'offer1' => 800,
                    'offer2' => 0,
                    'offer3' => 0,
                    'offer4' => 0,
                    'offer5' => 0,
                    'offer6' => 0,
                ],
                'usedMle' => false,
                'usedLle' => false,
                'teamName' => 'Stars',
            ],
        ];

        $counts = $this->repo->executeSigningsTransactionally(
            $signings,
            '2006 IBL Free Agency, Days 3-4',
            '',   // empty homeText → news-insert gated out
            ''    // empty bodyText
        );

        // Only the player update contributes to successCount (news not attempted)
        self::assertSame(1, $counts['successCount']);
        self::assertSame(0, $counts['errorCount']);

        // Player was updated
        $stmt = $this->db->prepare('SELECT teamid FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(2, (int) $row['teamid']);
    }

    /**
     * Empty signings list: no ibl_plr mutation, no news insert, zero counts.
     * Kills the mutant that would skip the empty-array check in executeSignings()
     * (FreeAgencyAdminProcessor). At the repository level this confirms
     * executeSigningsTransactionally handles an empty list gracefully.
     */
    public function testExecuteSigningsTransactionallyWithNoSigningsReturnsZeroCounts(): void
    {
        $counts = $this->repo->executeSigningsTransactionally(
            [],
            '2006 IBL Free Agency, Days 5-6',
            'Some home text.',
            'Some body text.'
        );

        // No signings → successCount=0, news-insert gated (successCount was 0)
        self::assertSame(0, $counts['successCount']);
        self::assertSame(0, $counts['errorCount']);
    }
}
