<?php

declare(strict_types=1);

namespace Tests\TrainingCampRatingsDiff;

use PHPUnit\Framework\TestCase;
use TrainingCampRatingsDiff\Contracts\TrainingCampRatingsDiffRepositoryInterface;
use TrainingCampRatingsDiff\RatingRow;
use TrainingCampRatingsDiff\TrainingCampRatingsDiffService;

class TrainingCampRatingsDiffServiceTest extends TestCase
{
    /** @var TrainingCampRatingsDiffRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private TrainingCampRatingsDiffRepositoryInterface $stubRepo;

    private TrainingCampRatingsDiffService $service;

    protected function setUp(): void
    {
        $this->stubRepo = $this->createStub(TrainingCampRatingsDiffRepositoryInterface::class);
        $this->service  = new TrainingCampRatingsDiffService($this->stubRepo);
    }

    private function buildService(?TrainingCampRatingsDiffRepositoryInterface $repo = null): TrainingCampRatingsDiffService
    {
        return new TrainingCampRatingsDiffService($repo ?? $this->stubRepo);
    }

    // ---------------------------------------------------------------------------
    // Helper: build a raw DB row with synthetic rating values
    // ---------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function buildDbRow(
        int $pid = 1,
        string $name = 'Test Player',
        string $pos = 'PG',
        int $teamid = 5,
        ?string $teamName = 'Metro Squad',
        bool $isNew = false,
        array $overrides = [],
    ): array {
        $row = [
            'pid'       => $pid,
            'name'      => $name,
            'pos'       => $pos,
            'teamid'       => $teamid,
            'team_name' => $teamName,
        ];

        foreach (TrainingCampRatingsDiffService::RATED_FIELDS as $i => $field) {
            $row[$field] = 50 + $i;
            if ($isNew) {
                $row['s_' . $field] = null;
            } else {
                $row['s_' . $field] = 40 + $i;
            }
        }

        return array_merge($row, $overrides);
    }

    // ---------------------------------------------------------------------------
    // getDiffs() — empty / null baseline
    // ---------------------------------------------------------------------------

    public function test_it_returns_empty_array_when_no_end_of_season_snapshot_exists(): void
    {
        $this->stubRepo->method('getLatestEndOfSeasonYear')->willReturn(null);

        $result = $this->service->getDiffs();

        self::assertSame([], $result);
    }

    // ---------------------------------------------------------------------------
    // getDiffs() — year resolution
    // ---------------------------------------------------------------------------

    public function test_it_resolves_baseline_year_from_repository_when_override_is_null(): void
    {
        $mockRepo = $this->createMock(TrainingCampRatingsDiffRepositoryInterface::class);
        $mockRepo->method('getLatestEndOfSeasonYear')->willReturn(2025);
        $mockRepo->expects($this->once())
            ->method('getDiffRows')
            ->with(2025, null)
            ->willReturn([]);

        $service = $this->buildService($mockRepo);
        $service->getDiffs(null);
    }

    public function test_it_uses_override_year_when_provided(): void
    {
        $mockRepo = $this->createMock(TrainingCampRatingsDiffRepositoryInterface::class);
        $mockRepo->expects($this->once())
            ->method('getDiffRows')
            ->with(2020, null)
            ->willReturn([]);

        $service = $this->buildService($mockRepo);
        $service->getDiffs(2020);
    }

    public function test_it_passes_filter_tid_through_to_repository(): void
    {
        $mockRepo = $this->createMock(TrainingCampRatingsDiffRepositoryInterface::class);
        $mockRepo->method('getLatestEndOfSeasonYear')->willReturn(2025);
        $mockRepo->expects($this->once())
            ->method('getDiffRows')
            ->with(2025, 7)
            ->willReturn([]);

        $service = $this->buildService($mockRepo);
        $service->getDiffs(null, 7);
    }

    // ---------------------------------------------------------------------------
    // getDiffs() — delta computation for all 21 fields
    // ---------------------------------------------------------------------------

    public function test_it_computes_correct_delta_for_each_of_the_21_rating_fields(): void
    {
        // Each field: after = 50+i, before = 40+i → delta = 10 for every field
        $dbRow = $this->buildDbRow(isNew: false);

        $this->stubRepo->method('getLatestEndOfSeasonYear')->willReturn(2025);
        $this->stubRepo->method('getDiffRows')->willReturn([$dbRow]);

        $rows = $this->service->getDiffs();

        self::assertCount(1, $rows);
        $ratingRow = $rows[0];

        self::assertCount(21, $ratingRow->deltas);

        foreach (TrainingCampRatingsDiffService::RATED_FIELDS as $i => $field) {
            $expectedAfter  = 50 + $i;
            $expectedBefore = 40 + $i;
            $expectedDelta  = 10;

            self::assertArrayHasKey($field, $ratingRow->deltas);
            $delta = $ratingRow->deltas[$field];

            self::assertSame($expectedAfter, $delta->after, "after mismatch for field $field");
            self::assertSame($expectedBefore, $delta->before, "before mismatch for field $field");
            self::assertSame($expectedDelta, $delta->delta, "delta mismatch for field $field");
        }
    }

    // ---------------------------------------------------------------------------
    // getDiffs() — sort order
    // ---------------------------------------------------------------------------

    public function test_it_ranks_real_rows_by_max_abs_delta_desc_sum_abs_delta_desc_name_asc(): void
    {
        // A: max=10, sum=20, name="Zulu"
        // B: max=10, sum=30, name="Alpha"  → ties on max with A; higher sum wins
        // C: max=5,  sum=99, name="Bravo"  → lower max loses despite higher sum
        // D: max=10, sum=30, name="Beta"   → ties A+B on max+sum; name tiebreaker: Alpha < Beta

        $rowA = $this->buildDbRow(1, 'Zulu');
        $rowB = $this->buildDbRow(2, 'Alpha');
        $rowC = $this->buildDbRow(3, 'Bravo');
        $rowD = $this->buildDbRow(4, 'Beta');

        // Manually set deltas to get precise aggregates. We override field values:
        // For simplicity, set one field to get the desired max and all others to 0.
        // maxAbsDelta = max of abs(deltas), sumAbsDelta = sum of abs(deltas).
        // We achieve each profile by setting s_oo so delta = desired, rest = 0.

        // A: oo after=10, s_oo before=0 → delta=10; rest after==before → delta=0
        //    → max=10, sum=10. We need sum=20 so add a second non-zero delta.
        //    oo: delta=10; od: delta=10 → max=10, sum=20
        $rowA['oo']   = 60; $rowA['s_oo']   = 50; // delta=10
        $rowA['od']   = 55; $rowA['s_od']   = 45; // delta=10
        // All other fields: make after == before
        foreach (TrainingCampRatingsDiffService::RATED_FIELDS as $field) {
            if ($field !== 'oo' && $field !== 'od') {
                $rowA[$field]       = 50;
                $rowA['s_' . $field] = 50;
            }
        }

        // B: oo: delta=10; od: delta=10; r_drive_off: delta=10 → max=10, sum=30
        $rowB['oo']          = 60; $rowB['s_oo']          = 50; // delta=10
        $rowB['od']          = 55; $rowB['s_od']          = 45; // delta=10
        $rowB['r_drive_off'] = 52; $rowB['s_r_drive_off'] = 42; // delta=10
        foreach (TrainingCampRatingsDiffService::RATED_FIELDS as $field) {
            if (!in_array($field, ['oo', 'od', 'r_drive_off'], true)) {
                $rowB[$field]       = 50;
                $rowB['s_' . $field] = 50;
            }
        }

        // C: oo: delta=5; rest 0 → max=5, sum=5
        $rowC['oo'] = 55; $rowC['s_oo'] = 50; // delta=5
        foreach (TrainingCampRatingsDiffService::RATED_FIELDS as $field) {
            if ($field !== 'oo') {
                $rowC[$field]       = 50;
                $rowC['s_' . $field] = 50;
            }
        }

        // D: same max+sum as B, name "Beta" > "Alpha" → sorted after B
        $rowD['oo']          = 60; $rowD['s_oo']          = 50; // delta=10
        $rowD['od']          = 55; $rowD['s_od']          = 45; // delta=10
        $rowD['r_drive_off'] = 52; $rowD['s_r_drive_off'] = 42; // delta=10
        foreach (TrainingCampRatingsDiffService::RATED_FIELDS as $field) {
            if (!in_array($field, ['oo', 'od', 'r_drive_off'], true)) {
                $rowD[$field]       = 50;
                $rowD['s_' . $field] = 50;
            }
        }

        $this->stubRepo->method('getLatestEndOfSeasonYear')->willReturn(2025);
        $this->stubRepo->method('getDiffRows')->willReturn([$rowA, $rowB, $rowC, $rowD]);

        $rows = $this->service->getDiffs();

        // Expected order: B (max=10,sum=30,name=Alpha), D (max=10,sum=30,name=Beta),
        //                 A (max=10,sum=20,name=Zulu), C (max=5,sum=5,name=Bravo)
        self::assertCount(4, $rows);
        self::assertSame('Alpha', $rows[0]->name);
        self::assertSame('Beta',  $rows[1]->name);
        self::assertSame('Zulu',  $rows[2]->name);
        self::assertSame('Bravo', $rows[3]->name);
    }

    // ---------------------------------------------------------------------------
    // getDiffs() — new player (rookie / no snapshot)
    // ---------------------------------------------------------------------------

    public function test_it_flags_is_new_player_when_baseline_snapshot_fields_are_null_and_sorts_them_to_the_end(): void
    {
        $real1 = $this->buildDbRow(1, 'Veteran Player', isNew: false);
        $real2 = $this->buildDbRow(2, 'Another Vet',   isNew: false);
        // Give real rows a big delta so they are clearly first
        $real1['oo'] = 90; $real1['s_oo'] = 50; // delta = 40
        $real2['oo'] = 80; $real2['s_oo'] = 50; // delta = 30
        $rookie = $this->buildDbRow(3, 'Rookie Player', isNew: true);

        $this->stubRepo->method('getLatestEndOfSeasonYear')->willReturn(2025);
        $this->stubRepo->method('getDiffRows')->willReturn([$real1, $real2, $rookie]);

        $rows = $this->service->getDiffs();

        self::assertCount(3, $rows);

        // Rookie is last
        $lastRow = $rows[2];
        self::assertSame('Rookie Player', $lastRow->name);
        self::assertTrue($lastRow->isNewPlayer);
        self::assertSame(0, $lastRow->maxAbsDelta);

        // All deltas for the rookie have null before/delta
        foreach ($lastRow->deltas as $delta) {
            self::assertNull($delta->before);
            self::assertNull($delta->delta);
        }

        // Real rows come first
        self::assertFalse($rows[0]->isNewPlayer);
        self::assertFalse($rows[1]->isNewPlayer);
    }

    // ---------------------------------------------------------------------------
    // getDiffs() — zero-delta player not filtered out
    // ---------------------------------------------------------------------------

    public function test_it_preserves_zero_delta_players_and_does_not_filter_them_out(): void
    {
        // All after values equal before values → all deltas = 0
        $row = $this->buildDbRow(1, 'Unchanged Player');
        foreach (TrainingCampRatingsDiffService::RATED_FIELDS as $field) {
            $row[$field]       = 50;
            $row['s_' . $field] = 50;
        }

        $this->stubRepo->method('getLatestEndOfSeasonYear')->willReturn(2025);
        $this->stubRepo->method('getDiffRows')->willReturn([$row]);

        $rows = $this->service->getDiffs();

        self::assertCount(1, $rows);
        self::assertSame(0, $rows[0]->maxAbsDelta);
        self::assertSame(0, $rows[0]->sumAbsDelta);
        self::assertFalse($rows[0]->isNewPlayer);
    }

    // ---------------------------------------------------------------------------
    // getBaselineYear()
    // ---------------------------------------------------------------------------

    public function test_get_baseline_year_returns_override_when_provided_else_repository_value(): void
    {
        $this->stubRepo->method('getLatestEndOfSeasonYear')->willReturn(2025);
        $service = $this->buildService();

        // With override: ignores repository
        self::assertSame(2019, $service->getBaselineYear(2019));

        // Without override: uses repository
        self::assertSame(2025, $service->getBaselineYear(null));
    }
}
