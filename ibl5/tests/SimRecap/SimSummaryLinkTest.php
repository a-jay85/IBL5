<?php

declare(strict_types=1);

namespace Tests\SimRecap;

use PHPUnit\Framework\TestCase;
use SimRecap\SimSummaryLink;

final class SimSummaryLinkTest extends TestCase
{
    public function testPathReturnsRelativeViewerRoute(): void
    {
        self::assertSame('simSummaries.php?sim=689', SimSummaryLink::path(689));
    }

    public function testPathComposesSimNumber(): void
    {
        self::assertSame('simSummaries.php?sim=1', SimSummaryLink::path(1));
    }

    public function testAbsoluteComposesSchemeHostPrefixAndPath(): void
    {
        self::assertSame(
            'https://iblhoops.net/ibl5/simSummaries.php?sim=689',
            SimSummaryLink::absolute(689, 'iblhoops.net'),
        );
    }

    public function testAbsoluteUsesProvidedHostNotConstant(): void
    {
        // Proves the class is pure: if absolute() secretly read IBL5_CANONICAL_HOST
        // (defined as '' in CI) instead of the $host parameter, the output would be
        // 'https:///ibl5/...' rather than the expected composed URL. Seeing the
        // explicit host in the result proves the parameter is used, not the constant.
        $result = SimSummaryLink::absolute(100, 'explicit-host.example.com');

        self::assertStringContainsString('explicit-host.example.com', $result);
        self::assertStringNotContainsString('https:///', $result);
    }
}
