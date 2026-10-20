<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use Player\CareerArc\CareerArcRepository;

/**
 * Tests CareerArcRepository against real MariaDB: pid-keyed season, move,
 * and draft reads plus the name-uniqueness guard on awards.
 */
#[Group('database')]
class CareerArcRepositoryTest extends DatabaseTestCase
{
    private CareerArcRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new CareerArcRepository($this->db);
    }

    /**
     * @param array<string, int|string> $overrides
     */
    private function insertMove(int $pid, int $type, int $fromTid, int $toTid, array $overrides = []): void
    {
        $this->insertRow('ibl_jsb_transactions', array_merge([
            'season_year' => 2030,
            'transaction_month' => 2,
            'transaction_day' => 10,
            'transaction_type' => $type,
            'pid' => $pid,
            'player_name' => 'Arc Test Mover',
            'from_teamid' => $fromTid,
            'to_teamid' => $toTid,
            'is_draft_pick' => 0,
        ], $overrides));
    }

    public function testSeasonRowsOrderedByYearForPid(): void
    {
        $this->insertHistRow(200000080, 'Arc Test Seasons', 2024);
        $this->insertHistRow(200000080, 'Arc Test Seasons', 2022);
        $this->insertHistRow(200000080, 'Arc Test Seasons', 2023);
        $this->insertHistRow(200000081, 'Arc Test Other', 2023);

        $rows = $this->repo->getSeasonRows(200000080);

        self::assertSame([2022, 2023, 2024], array_column($rows, 'year'));
    }

    public function testMovesReturnsOnlyTradesAndWaiverClaims(): void
    {
        $this->insertRow('ibl_team_info', [
            'teamid' => 91,
            'team_city' => 'Arc',
            'team_name' => 'ArcTestTeam',
        ]);
        $this->insertMove(200000082, 1, 1, 0, ['transaction_day' => 1]);
        $this->insertMove(200000082, 2, 98, 91, ['transaction_day' => 2]);
        $this->insertMove(200000082, 3, 0, 91, ['transaction_day' => 3]);
        $this->insertMove(200000082, 4, 91, 0, ['transaction_day' => 4]);

        $moves = $this->repo->getMoves(200000082);

        self::assertCount(2, $moves);
        self::assertSame([2, 3], array_column($moves, 'transaction_type'));
        self::assertSame('ArcTestTeam', $moves[0]['to_team_name']);
        self::assertNull($moves[0]['from_team_name']);
    }

    public function testMovesReturnsEmptyForPidZero(): void
    {
        $this->insertMove(0, 2, 1, 2);

        self::assertSame([], $this->repo->getMoves(0));
    }

    public function testPlayerFactsReturnsNullForUnknownPid(): void
    {
        self::assertNull($this->repo->getPlayerFacts(999999999));

        $this->insertTestPlayer(200000083, 'Arc Test Drafted', [
            'draftyear' => 2020,
            'draftround' => 1,
            'draftpickno' => 7,
            'draftedby' => 'Arcs',
        ]);

        $facts = $this->repo->getPlayerFacts(200000083);

        self::assertNotNull($facts);
        self::assertSame('Arc Test Drafted', $facts['name']);
        self::assertSame(2020, $facts['draftyear']);
        self::assertSame(1, $facts['draftround']);
        self::assertSame(7, $facts['draftpickno']);
        self::assertSame('Arcs', $facts['draftedby']);
    }

    public function testAwardsNullWhenNameSharedByTwoPlayers(): void
    {
        $this->insertTestPlayer(200000084, 'Arc Test Twin');
        $this->insertTestPlayer(200000085, 'Arc Test Twin');
        $this->insertAwardRow('Arc Test Twin', 'MVP', 2021);

        self::assertNull($this->repo->getAwardsForUniqueName('Arc Test Twin'));
    }

    public function testAwardsReturnedWhenNameUnique(): void
    {
        $this->insertTestPlayer(200000086, 'Arc Test Solo');
        $this->insertAwardRow('Arc Test Solo', 'All-Star', 2022);
        $this->insertAwardRow('Arc Test Solo', 'MVP', 2021);

        $awards = $this->repo->getAwardsForUniqueName('Arc Test Solo');

        self::assertSame(
            [['year' => 2021, 'award' => 'MVP'], ['year' => 2022, 'award' => 'All-Star']],
            $awards
        );
    }

    public function testAwardsNameWithApostropheBindsSafely(): void
    {
        $this->insertTestPlayer(200000087, "D'Angelo Arc Test");
        $this->insertAwardRow("D'Angelo Arc Test", 'Rookie of the Year', 2020);

        $awards = $this->repo->getAwardsForUniqueName("D'Angelo Arc Test");

        self::assertNotNull($awards);
        self::assertCount(1, $awards);
        self::assertSame('Rookie of the Year', $awards[0]['award']);
    }
}
