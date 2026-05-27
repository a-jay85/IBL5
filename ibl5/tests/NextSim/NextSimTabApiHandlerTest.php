<?php

declare(strict_types=1);

namespace Tests\NextSim;

use NextSim\NextSimTabApiHandler;
use PHPUnit\Framework\TestCase;

class NextSimTabApiHandlerTest extends TestCase
{
    public function testExtractValidatedPositionDefaultsToPg(): void
    {
        self::assertSame('PG', NextSimTabApiHandler::extractValidatedPosition([]));
    }

    public function testExtractValidatedPositionUsesValidParam(): void
    {
        self::assertSame('SF', NextSimTabApiHandler::extractValidatedPosition(['position' => 'SF']));
        self::assertSame('C', NextSimTabApiHandler::extractValidatedPosition(['position' => 'C']));
        self::assertSame('SG', NextSimTabApiHandler::extractValidatedPosition(['position' => 'SG']));
        self::assertSame('PF', NextSimTabApiHandler::extractValidatedPosition(['position' => 'PF']));
    }

    public function testExtractValidatedPositionRejectsInvalidAndFallsToPg(): void
    {
        self::assertSame('PG', NextSimTabApiHandler::extractValidatedPosition(['position' => 'ZZ']));
        self::assertSame('PG', NextSimTabApiHandler::extractValidatedPosition(['position' => 'pg']));
        self::assertSame('PG', NextSimTabApiHandler::extractValidatedPosition(['position' => '']));
    }

    public function testExtractValidatedPositionRejectsNonStringAndFallsToPg(): void
    {
        self::assertSame('PG', NextSimTabApiHandler::extractValidatedPosition(['position' => ['array']]));
        self::assertSame('PG', NextSimTabApiHandler::extractValidatedPosition(['position' => 123]));
    }

    public function testExtractValidatedTeamidDefaultsToZero(): void
    {
        self::assertSame(0, NextSimTabApiHandler::extractValidatedTeamid([]));
    }

    public function testExtractValidatedTeamidParsesStringToInt(): void
    {
        self::assertSame(5, NextSimTabApiHandler::extractValidatedTeamid(['teamid' => '5']));
    }

    public function testExtractValidatedTeamidRejectsNonString(): void
    {
        self::assertSame(0, NextSimTabApiHandler::extractValidatedTeamid(['teamid' => ['array']]));
        self::assertSame(0, NextSimTabApiHandler::extractValidatedTeamid(['teamid' => 42]));
    }
}
