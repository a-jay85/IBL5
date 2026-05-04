<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Updater\Steps\PreseasonCleanupRepository;

class PreseasonCleanupRepositoryTest extends DatabaseTestCase
{
    private PreseasonCleanupRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new PreseasonCleanupRepository($this->db);
    }

    public function testHasPreseasonBoxScoresReturnsFalseWithOnlyOctoberData(): void
    {
        $this->insertTeamBoxscoreRow('2098-10-15', 'OctGame', 1, 5, 6);

        self::assertFalse($this->repo->hasPreseasonBoxScores(2098));
    }

    public function testHasPreseasonBoxScoresReturnsTrueWithSeptemberData(): void
    {
        $this->insertTeamBoxscoreRow('2098-09-20', 'SepGame', 1, 5, 6);

        self::assertTrue($this->repo->hasPreseasonBoxScores(2098));
    }

    public function testDeletePreseasonSimDatesPreservesOctoberRows(): void
    {
        $this->insertRow('ibl_sim_dates', [
            'sim' => 900,
            'start_date' => '2098-09-20',
            'end_date' => '2098-09-25',
        ]);
        $this->insertRow('ibl_sim_dates', [
            'sim' => 901,
            'start_date' => '2098-10-05',
            'end_date' => '2098-10-10',
        ]);

        $this->repo->deletePreseasonSimDates(2098);

        $result = $this->db->query("SELECT sim FROM ibl_sim_dates WHERE sim IN (900, 901)");
        self::assertNotFalse($result);
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        $sims = array_column($rows, 'sim');
        self::assertNotContains(900, $sims, 'September sim date should be deleted');
        self::assertContains(901, $sims, 'October sim date should be preserved');
    }
}
