<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use TrainingCampRatingsDiff\TrainingCampRatingsDiffRepository;
use Updater\Steps\RefreshIblHistStep;

/**
 * Guards the phase-rank order shared by the two live snapshot_phase lists.
 *
 * TrainingCampRatingsDiffRepository::getBaselinePhase() uses a FIELD() list to pick the
 * ratings baseline for a season. RefreshIblHistStep::SELECT_SQL uses a CASE to break
 * `stats_gm DESC` ties when it collapses snapshots into ibl_hist. The two have different
 * jobs and are deliberately not merged. The shared invariant is that the archive-named
 * playoff rounds rank latest-in-season first, matching the header intent of
 * ibl5/migrations/103_ibl_hist_canonical_snapshot.sql (backlog#782).
 *
 * Intentional differences, not guarded: `playoffs` (ibl_hist rank 0, TC second),
 * `end-of-season` (ibl_hist rank 1, TC near-last because it can hold offseason ratings),
 * `mid-season` (TC last, ibl_hist ELSE 99), and `post-heat` / `heat-*` (ibl_hist only).
 * Applied migrations 103 through 109 keep the old CASE and are excluded on purpose
 * (immutable history).
 */
final class PhaseRankOrderConsistencyTest extends TestCase
{
    private const array GUARDED = [
        'finals', 'conf-finals-gm4-7', 'conf-finals-gm1-3',
        'playoffs-rd2-gm4-7', 'playoffs-rd2-gm1-3',
        'playoffs-rd1-gm4-7', 'playoffs-rd1-gm1-3',
    ];

    public function testLiveCaseRanksAreStrictlyIncreasingInTextOrder(): void
    {
        $ranks = self::extractCaseRanks(self::liveSelectSql());

        self::assertCount(14, $ranks, 'The live phase-rank CASE must list 14 phases.');

        $previous = null;
        foreach ($ranks as $phase => $rank) {
            if ($previous !== null) {
                self::assertGreaterThan($previous, $rank, "Rank of '{$phase}' must exceed the arm above it.");
            }
            $previous = $rank;
        }
    }

    public function testBothListsContainEveryGuardedPhase(): void
    {
        $expected = self::GUARDED;
        sort($expected);

        $live = array_keys(self::extractCaseRanks(self::liveSelectSql()));
        $tc = self::extractTcFieldOrder(self::tcRepositorySource());

        self::assertSame($expected, self::sortedGuarded($live), 'Live CASE is missing a guarded phase.');
        self::assertSame($expected, self::sortedGuarded($tc), 'TC FIELD() list is missing a guarded phase.');
    }

    public function testGuardedPhasesShareRelativeOrderAcrossLiveLists(): void
    {
        $live = self::guardedOrder(array_keys(self::extractCaseRanks(self::liveSelectSql())));
        $tc = self::guardedOrder(self::extractTcFieldOrder(self::tcRepositorySource()));

        self::assertSame(
            $tc,
            $live,
            'backlog#782: the playoff-round order in RefreshIblHistStep::SELECT_SQL diverged from '
            . 'TrainingCampRatingsDiffRepository::getBaselinePhase(). '
            . 'Fix the order in one file to match the other (PhaseRankOrderConsistencyTest).',
        );
        self::assertSame(self::GUARDED, $live);
        self::assertSame(self::GUARDED, $tc);
    }

    /**
     * Negative path: the pre-#782 CASE must compare unequal to the TC order, so the
     * comparison in testGuardedPhasesShareRelativeOrderAcrossLiveLists can actually fail.
     * Mutation: make guardedOrder() sort or return self::GUARDED and this fails.
     */
    public function testPreFixOrderIsDetectedAsDrift(): void
    {
        $preFixSql = <<<'SQL'
CASE s.snapshot_phase
  WHEN 'playoffs'            THEN  0
  WHEN 'end-of-season'       THEN  1
  WHEN 'finals'              THEN  2
  WHEN 'post-heat'           THEN  3
  WHEN 'heat-finals'         THEN  4
  WHEN 'heat-end'            THEN  5
  WHEN 'playoffs-rd2-gm4-7'  THEN  6
  WHEN 'playoffs-rd2-gm1-3'  THEN  7
  WHEN 'playoffs-rd1-gm4-7'  THEN  8
  WHEN 'playoffs-rd1-gm1-3'  THEN  9
  WHEN 'conf-finals-gm4-7'   THEN 10
  WHEN 'conf-finals-gm1-3'   THEN 11
  WHEN 'heat-wb'             THEN 12
  WHEN 'heat-lb'             THEN 13
  ELSE 99
SQL;

        $preFix = self::guardedOrder(array_keys(self::extractCaseRanks($preFixSql)));
        $tc = self::guardedOrder(self::extractTcFieldOrder(self::tcRepositorySource()));

        self::assertNotSame($tc, $preFix, 'The pre-#782 CASE order must register as drift.');
    }

    public function testCiSeedCaseMatchesLiveCase(): void
    {
        $seed = file_get_contents(dirname(__DIR__, 2) . '/e2e/fixtures/ci-seed.sql');
        self::assertNotFalse($seed, 'ci-seed.sql must be readable');

        self::assertSame(
            self::extractCaseRanks(self::liveSelectSql()),
            self::extractCaseRanks($seed),
            'ci-seed.sql phase-rank CASE drifted from RefreshIblHistStep::SELECT_SQL.',
        );
    }

    public function testPromotePriorSeasonTestLocalCaseMatchesLiveCase(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/DatabaseIntegration/UpdateAllTheThings/PromotePriorSeasonSnapshotTest.php',
        );
        self::assertNotFalse($source, 'PromotePriorSeasonSnapshotTest.php must be readable');

        self::assertSame(
            self::extractCaseRanks(self::liveSelectSql()),
            self::extractCaseRanks($source),
            'The test-local CASE in PromotePriorSeasonSnapshotTest drifted from RefreshIblHistStep::SELECT_SQL.',
        );
    }

    private static function liveSelectSql(): string
    {
        $value = (new \ReflectionClassConstant(RefreshIblHistStep::class, 'SELECT_SQL'))->getValue();
        self::assertIsString($value);

        return $value;
    }

    private static function tcRepositorySource(): string
    {
        $file = (new \ReflectionClass(TrainingCampRatingsDiffRepository::class))->getFileName();
        self::assertIsString($file);
        $source = file_get_contents($file);
        self::assertNotFalse($source);

        return $source;
    }

    /**
     * @return array<string, int> phase => rank, in textual order
     */
    private static function extractCaseRanks(string $sql): array
    {
        self::assertSame(
            1,
            preg_match('/CASE s\.snapshot_phase(.*?)ELSE 99/s', $sql, $m),
            'phase-rank CASE not found',
        );
        preg_match_all("/WHEN '([a-z0-9-]+)'\s+THEN\s+(\d+)/", $m[1], $arms, PREG_SET_ORDER);

        $ranks = [];
        foreach ($arms as $arm) {
            $ranks[$arm[1]] = (int) $arm[2];
        }

        return $ranks;
    }

    /**
     * @return list<string> phases in FIELD() order
     */
    private static function extractTcFieldOrder(string $source): array
    {
        self::assertSame(
            1,
            preg_match('/FIELD\(snapshot_phase,(.*?)\)\s+AS phase_rank/s', $source, $m),
            'getBaselinePhase FIELD() list not found',
        );
        preg_match_all("/'([a-z0-9-]+)'/", $m[1], $names);

        return $names[1];
    }

    /**
     * @param list<string> $phases
     * @return list<string>
     */
    private static function guardedOrder(array $phases): array
    {
        return array_values(array_filter(
            $phases,
            static fn (string $p): bool => in_array($p, self::GUARDED, true),
        ));
    }

    /**
     * @param list<string> $phases
     * @return list<string>
     */
    private static function sortedGuarded(array $phases): array
    {
        $guarded = self::guardedOrder($phases);
        sort($guarded);

        return $guarded;
    }
}
