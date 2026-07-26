<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\FreeAgencyCapCalculatorFactory;
use FreeAgency\Contracts\FreeAgencyCapCalculatorInterface;
use PHPUnit\Framework\TestCase;
use Season\Season;
use Team\Team;
use Tests\WideUnit\Mocks\MockDatabase;

class FreeAgencyCapCalculatorFactoryTest extends TestCase
{
    public function testForTeamReturnsCapCalculatorInterface(): void
    {
        $factory = new FreeAgencyCapCalculatorFactory(new MockDatabase());

        /** @var Team&\PHPUnit\Framework\MockObject\Stub $team */
        $team = $this->createStub(Team::class);
        /** @var Season&\PHPUnit\Framework\MockObject\Stub $season */
        $season = $this->createStub(Season::class);

        $result = $factory->forTeam($team, $season);

        $this->assertInstanceOf(FreeAgencyCapCalculatorInterface::class, $result);
    }

    public function testForTeamReturnsFreshInstanceEachCall(): void
    {
        $factory = new FreeAgencyCapCalculatorFactory(new MockDatabase());

        /** @var Team&\PHPUnit\Framework\MockObject\Stub $teamA */
        $teamA = $this->createStub(Team::class);
        /** @var Team&\PHPUnit\Framework\MockObject\Stub $teamB */
        $teamB = $this->createStub(Team::class);
        /** @var Season&\PHPUnit\Framework\MockObject\Stub $season */
        $season = $this->createStub(Season::class);

        $first  = $factory->forTeam($teamA, $season);
        $second = $factory->forTeam($teamB, $season);

        $this->assertNotSame($first, $second);
    }
}
