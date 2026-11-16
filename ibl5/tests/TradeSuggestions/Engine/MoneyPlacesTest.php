<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\MoneyPlaces;

final class MoneyPlacesTest extends TestCase
{
    public function testRateEqualsCapRoomOverageRate(): void
    {
        self::assertEqualsWithDelta(1 / 1400, MoneyPlaces::placesPerSalaryUnit(), 1e-12);
        self::assertEqualsWithDelta(0.5, MoneyPlaces::fromSalary(700), 1e-12);
    }

    public function testFromSalaryIsSignedAndLinear(): void
    {
        self::assertEqualsWithDelta(-1.0, MoneyPlaces::fromSalary(-1400), 1e-12);
        self::assertEqualsWithDelta(2.0, MoneyPlaces::fromSalary(2800), 1e-12);
        self::assertSame(0.0, MoneyPlaces::fromSalary(0));
    }
}
