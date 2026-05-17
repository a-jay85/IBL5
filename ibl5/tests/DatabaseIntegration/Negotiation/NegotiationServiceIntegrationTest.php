<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Negotiation;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Negotiation\NegotiationDemandCalculator;
use Negotiation\NegotiationRepository;
use Negotiation\NegotiationService;
use Negotiation\NegotiationValidator;
use Repositories\SalaryCapRepository;

#[Group('database')]
class NegotiationServiceIntegrationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['SERVER_NAME'] = 'localhost';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['SERVER_NAME']);
        parent::tearDown();
    }

    public function testSuccessfulNegotiationShowsDemandsForm(): void
    {
        $pid = 200050001;
        $this->seedEligiblePlayer($pid, 'Nego Test Star', 1, 'PG', 5);

        $service = $this->buildService();
        $output = $service->processNegotiation($pid, 'Metros', 'nego_');

        self::assertStringContainsString('Nego Test Star', $output);
        self::assertStringNotContainsString('not available during free agency', $output);
        self::assertStringNotContainsString('is not on your team', $output);
    }

    public function testFreeAgencyPhaseBlocksNegotiation(): void
    {
        $pid = 200050002;
        $this->seedEligiblePlayer($pid, 'Nego Test FA Block', 1, 'SG', 4);

        $season = new \Season\Season($this->db);
        $season->phase = 'Free Agency';

        $service = $this->buildService($season);
        $output = $service->processNegotiation($pid, 'Metros', 'nego_');

        self::assertStringContainsString('not available during free agency', $output);
    }

    public function testNegotiationRejectsForeignTeamPlayer(): void
    {
        $pid = 200050003;
        $this->seedEligiblePlayer($pid, 'Nego Test Foreign', 2, 'SF', 3);

        $service = $this->buildService();
        $output = $service->processNegotiation($pid, 'Metros', 'nego_');

        self::assertStringContainsString('is not on your team', $output);
    }

    public function testDemandsReflectPlayerRatings(): void
    {
        $lowPid = 200050004;
        $highPid = 200050005;

        $this->seedEligiblePlayer($lowPid, 'Nego Lowly', 1, 'PG', 5, 30);
        $this->seedEligiblePlayer($highPid, 'Nego Elite', 1, 'SG', 7, 95);

        $service = $this->buildService();
        $lowOutput = $service->processNegotiation($lowPid, 'Metros', 'nego_');
        $highOutput = $service->processNegotiation($highPid, 'Metros', 'nego_');

        self::assertStringContainsString('Nego Lowly', $lowOutput);
        self::assertStringContainsString('Nego Elite', $highOutput);
        self::assertNotSame($lowOutput, $highOutput);
    }

    private function buildService(?\Season\Season $season = null): NegotiationService
    {
        return new NegotiationService(
            $this->db,
            new NegotiationRepository($this->db, new SalaryCapRepository($this->db)),
            new NegotiationValidator($this->db, $season),
            new NegotiationDemandCalculator($this->db, new SalaryCapRepository($this->db)),
        );
    }

    private function seedEligiblePlayer(
        int $pid,
        string $name,
        int $teamId,
        string $pos,
        int $exp,
        int $ratingBase = 60,
    ): void {
        $this->insertTestPlayer($pid, $name, [
            'teamid' => $teamId,
            'pos' => $pos,
            'exp' => $exp,
            'bird' => min($exp, 3),
            'cy' => 1,
            'cyt' => 1,
            'salary_yr1' => 1500,
            'salary_yr2' => 0,
            'r_fga' => $ratingBase,
            'r_fgp' => $ratingBase,
            'r_fta' => $ratingBase,
            'r_ftp' => $ratingBase,
            'r_3ga' => $ratingBase,
            'r_3gp' => $ratingBase,
            'r_orb' => $ratingBase,
            'r_drb' => $ratingBase,
            'r_ast' => $ratingBase,
            'r_stl' => $ratingBase,
            'r_tvr' => $ratingBase,
            'r_blk' => $ratingBase,
            'r_foul' => $ratingBase,
            'oo' => $ratingBase,
            'od' => $ratingBase,
            'r_drive_off' => $ratingBase,
            'dd' => $ratingBase,
            'po' => $ratingBase,
            'pd' => $ratingBase,
            'r_trans_off' => $ratingBase,
            'td' => $ratingBase,
        ]);
    }
}
