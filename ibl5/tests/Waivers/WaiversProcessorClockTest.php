<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Tests\Clock\FixedClock;
use Validation\ValidationResult;
use Waivers\Contracts\WaiversRepositoryInterface;
use Waivers\Contracts\WaiversValidatorInterface;
use Waivers\WaiversProcessor;

/**
 * Pins the injected clock so the waiver drop timestamp is deterministic.
 */
class WaiversProcessorClockTest extends TestCase
{
    public function testProcessDropStampsWaiverTimeFromInjectedClock(): void
    {
        // One second before the 24-hour waiver window would expire for a drop at 1_700_000_000.
        $pinnedNow = 1_700_000_000 + 86400 - 1;

        $repo = $this->createMock(WaiversRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('dropPlayerToWaivers')
            ->with(1, $pinnedNow)
            ->willReturn(true);

        $validator = self::createStub(WaiversValidatorInterface::class);
        $validator->method('validateDrop')->willReturn(ValidationResult::success());
        $lookup = self::createStub(\Repositories\Contracts\PlayerLookupRepositoryInterface::class);
        $lookup->method('getPlayerByID')->willReturn(['name' => 'Test Player', 'pid' => 1]);

        $processor = new WaiversProcessor(
            $repo,
            self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class),
            $lookup,
            $validator,
            self::createStub(\Topics\News\NewsRepository::class),
            self::createStub(\mysqli::class),
            null,
            null,
            new FixedClock($pinnedNow)
        );

        $result = $processor->processDrop(1, 'Test Team', 3, 5000);

        self::assertTrue($result['success']);
    }
}
