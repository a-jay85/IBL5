<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use DraftHistory\DraftHistoryRepository;

/**
 * Tests DraftHistoryRepository against real MariaDB — draft picks by year/team.
 */
#[Group('database')]
class DraftHistoryRepositoryTest extends DatabaseTestCase
{
    private DraftHistoryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new DraftHistoryRepository($this->db);
    }

    public function testGetFirstDraftYearReturns1988(): void
    {
        self::assertSame(1988, $this->repo->getFirstDraftYear());
    }

    public function testGetLastDraftYearReturnsInt(): void
    {
        // Insert a test player with a known draft year to avoid dependency on production data
        $this->insertTestPlayer(200030003, 'DH LastYear', [
            'draftyear' => 2050,
            'draftround' => 1,
            'draftpickno' => 1,
            'draftedby' => 'Metros',
        ]);

        $year = $this->repo->getLastDraftYear();

        self::assertIsInt($year);
        self::assertGreaterThanOrEqual(2050, $year);
    }

    public function testGetDraftPicksByYearReturnsRows(): void
    {
        $this->insertTestPlayer(200030001, 'DH YearTest', [
            'draftyear' => 2098,
            'draftround' => 1,
            'draftpickno' => 5,
            'draftedby' => 'Metros',
            'college' => 'Test U',
        ]);

        $picks = $this->repo->getDraftPicksByYear(2098);

        self::assertNotSame([], $picks);

        $found = false;
        foreach ($picks as $pick) {
            if ($pick['pid'] === 200030001) {
                $found = true;
                self::assertSame('DH YearTest', $pick['name']);
                self::assertSame(1, $pick['draftround']);
                self::assertSame(5, $pick['draftpickno']);
                self::assertSame('Metros', $pick['draftedby']);
                self::assertArrayHasKey('color1', $pick);
                self::assertArrayHasKey('team_city', $pick);
                break;
            }
        }
        self::assertTrue($found, 'Inserted player not found in getDraftPicksByYear');
    }

    public function testGetDraftPicksByYearReturnsEmptyForUnknown(): void
    {
        $picks = $this->repo->getDraftPicksByYear(1850);

        self::assertSame([], $picks);
    }

    public function testGetDraftPicksByTeamReturnsRows(): void
    {
        $this->insertTestPlayer(200030002, 'DH TeamTest', [
            'draftyear' => 2097,
            'draftround' => 2,
            'draftpickno' => 10,
            'draftedby' => 'Metros',
        ]);

        $picks = $this->repo->getDraftPicksByTeam('Metros');

        self::assertNotSame([], $picks);

        $found = false;
        foreach ($picks as $pick) {
            if ($pick['pid'] === 200030002) {
                $found = true;
                self::assertSame('DH TeamTest', $pick['name']);
                self::assertSame(2097, $pick['draftyear']);
                break;
            }
        }
        self::assertTrue($found, 'Inserted player not found in getDraftPicksByTeam');
    }

    public function testGetDraftPicksByYearBreaksSlotTiesByPidAscending(): void
    {
        $this->insertTestPlayer(200139152, 'Tie 1391 B', ['draftyear' => 2098, 'draftround' => 1, 'draftpickno' => 5, 'draftedby' => 'Tie1391Team']);
        $this->insertTestPlayer(200139151, 'Tie 1391 A', ['draftyear' => 2098, 'draftround' => 1, 'draftpickno' => 5, 'draftedby' => 'Tie1391Team']);
        $this->insertTestPlayer(200139153, 'Tie 1391 C', ['draftyear' => 2098, 'draftround' => 1, 'draftpickno' => 4, 'draftedby' => 'Tie1391Team']);

        $rows = $this->repo->getDraftPicksByYear(2098);

        $ordered = array_values(array_filter(array_column($rows, 'pid'), static fn ($p): bool => in_array($p, [200139151, 200139152, 200139153], true)));
        self::assertSame([200139153, 200139151, 200139152], $ordered);
    }

    public function testGetDraftPicksByTeamBreaksSlotTiesByPidAscending(): void
    {
        $this->insertTestPlayer(200139162, 'Tie 1391 B', ['draftyear' => 2098, 'draftround' => 1, 'draftpickno' => 5, 'draftedby' => 'Tie1391Team']);
        $this->insertTestPlayer(200139161, 'Tie 1391 A', ['draftyear' => 2098, 'draftround' => 1, 'draftpickno' => 5, 'draftedby' => 'Tie1391Team']);
        $this->insertTestPlayer(200139163, 'Tie 1391 C', ['draftyear' => 2099, 'draftround' => 1, 'draftpickno' => 5, 'draftedby' => 'Tie1391Team']);

        $rows = $this->repo->getDraftPicksByTeam('Tie1391Team');

        $ordered = array_values(array_filter(array_column($rows, 'pid'), static fn ($p): bool => in_array($p, [200139161, 200139162, 200139163], true)));
        self::assertSame([200139163, 200139161, 200139162], $ordered);
    }
}
