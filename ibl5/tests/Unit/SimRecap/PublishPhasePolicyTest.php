<?php

declare(strict_types=1);

namespace Tests\Unit\SimRecap;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimRecap\PublishPhasePolicy;

final class PublishPhasePolicyTest extends TestCase
{
    public function testAllowsRegularSeason(): void
    {
        self::assertTrue(PublishPhasePolicy::isPublishable('Regular Season'));
    }

    public function testRefusesHeat(): void
    {
        self::assertFalse(PublishPhasePolicy::isPublishable('HEAT'));
    }

    public function testRefusesPlayoffs(): void
    {
        self::assertFalse(PublishPhasePolicy::isPublishable('Playoffs'));
    }

    #[DataProvider('nearMissProvider')]
    public function testRefusesNearMissPhaseStrings(string $phase): void
    {
        self::assertFalse(PublishPhasePolicy::isPublishable($phase));
    }

    /** @return list<array{string}> */
    public static function nearMissProvider(): array
    {
        return [['regular season'], ['Regular Season '], ['Regular'], ['']];
    }
}
