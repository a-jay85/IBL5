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
        $team = self::createStub(Team::class);
        /** @var Season&\PHPUnit\Framework\MockObject\Stub $season */
        $season = self::createStub(Season::class);

        $result = $factory->forTeam($team, $season);

        // @phpstan-ignore-next-line (return type is the interface — asserting it is intentional contract test)
        $this->assertInstanceOf(FreeAgencyCapCalculatorInterface::class, $result);
    }

    public function testForTeamReturnsFreshInstanceEachCall(): void
    {
        $factory = new FreeAgencyCapCalculatorFactory(new MockDatabase());

        /** @var Team&\PHPUnit\Framework\MockObject\Stub $teamA */
        $teamA = self::createStub(Team::class);
        /** @var Team&\PHPUnit\Framework\MockObject\Stub $teamB */
        $teamB = self::createStub(Team::class);
        /** @var Season&\PHPUnit\Framework\MockObject\Stub $season */
        $season = self::createStub(Season::class);

        $first  = $factory->forTeam($teamA, $season);
        $second = $factory->forTeam($teamB, $season);

        $this->assertNotSame($first, $second);
    }
}
