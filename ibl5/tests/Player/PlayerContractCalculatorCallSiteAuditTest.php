<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Player\Contract\PlayerContractCalculator;
use Player\Player;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\TestDataFactory;
use Waivers\WaiversProcessor;

/**
 * Regression guardrails: call-sites that deliberately remain phase-blind
 * must not accidentally inject a {@see \Season\Season} into their internal
 * {@see PlayerContractCalculator}.
 *
 * If a future change injects a Season into Player or WaiversProcessor's
 * constructor-created calculator, these tests catch it immediately so the
 * deliberate phase-blindness in those paths is not silently overridden.
 */
class PlayerContractCalculatorCallSiteAuditTest extends TestCase
{
    public function testPlayerFacadeIsPhaseBlind(): void
    {
        $mockDb = new MockDatabase();
        $player = Player::withPlrRow($mockDb, TestDataFactory::createPlayer(['pid' => 1, 'cy' => 2]));

        $calcProp = new \ReflectionProperty($player, 'contractCalculator');
        /** @var PlayerContractCalculator $calc */
        $calc = $calcProp->getValue($player);

        $seasonProp = new \ReflectionProperty($calc, 'season');
        self::assertNull(
            $seasonProp->getValue($calc),
            'Player must create a phase-blind PlayerContractCalculator (season=null)'
        );
    }

    public function testWaiversProcessorIsPhaseBlind(): void
    {
        $mockDb = new MockDatabase();
        $processor = new WaiversProcessor(
            self::createStub(\Waivers\Contracts\WaiversRepositoryInterface::class),
            self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class),
            self::createStub(\Repositories\Contracts\PlayerLookupRepositoryInterface::class),
            self::createStub(\Waivers\Contracts\WaiversValidatorInterface::class),
            self::createStub(\Topics\News\NewsRepository::class),
            $mockDb,
            self::createStub(\Psr\Log\LoggerInterface::class),
        );

        $calcProp = new \ReflectionProperty($processor, 'contractCalculator');
        /** @var PlayerContractCalculator $calc */
        $calc = $calcProp->getValue($processor);

        $seasonProp = new \ReflectionProperty($calc, 'season');
        self::assertNull(
            $seasonProp->getValue($calc),
            'WaiversProcessor must create a phase-blind PlayerContractCalculator (season=null)'
        );
    }
}
