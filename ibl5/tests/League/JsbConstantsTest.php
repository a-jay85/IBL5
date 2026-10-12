<?php

declare(strict_types=1);

namespace Tests\League;

use PHPUnit\Framework\TestCase;

final class JsbConstantsTest extends TestCase
{
    private const CLASSES_DIR = __DIR__ . '/../../classes';

    /**
     * A file that still declares `class JSB` makes the ReflectionClass below
     * throw ReflectionException, and a move out of League/ breaks the
     * namespace and file-suffix assertions.
     */
    public function testLeagueJsbConstantsResolvesWithConstants(): void
    {
        $ref = new \ReflectionClass(\League\JsbConstants::class);
        self::assertSame('League', $ref->getNamespaceName());
        foreach (['PLAYER_POSITIONS', 'PLAYOFF_MONTH', 'WAIVERS_ORDINAL'] as $constant) {
            self::assertTrue($ref->hasConstant($constant), "League\\JsbConstants lacks {$constant}");
        }
        self::assertStringEndsWith('/classes/League/JsbConstants.php', (string) $ref->getFileName());
    }

    public function testOldLeagueJsbNameDoesNotResolve(): void
    {
        self::assertFalse(class_exists('League\\JSB'), 'League\\JSB still resolves; rename to League\\JsbConstants is incomplete');
        self::assertFileDoesNotExist(self::CLASSES_DIR . '/League/JSB.php');
    }
}
