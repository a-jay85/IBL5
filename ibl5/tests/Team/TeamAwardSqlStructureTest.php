<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Repositories\TeamAwardSql;

/**
 * TeamAwardSql is a constants-only holder. Each builder owns its own WHERE
 * predicates and bind order, so a shared fragment must never carry a state
 * or a bind placeholder.
 */
class TeamAwardSqlStructureTest extends TestCase
{
    #[Test]
    public function teamAwardSqlIsFinalWithNoPropertiesOrMethods(): void
    {
        $class = new \ReflectionClass(TeamAwardSql::class);

        self::assertTrue($class->isFinal(), 'TeamAwardSql must be final');
        self::assertSame([], $class->getProperties(), 'TeamAwardSql must declare no properties');
        self::assertSame([], $class->getMethods(), 'TeamAwardSql must declare no methods');
        self::assertNull($class->getConstructor(), 'TeamAwardSql must have no constructor');
    }

    #[Test]
    public function teamAwardSqlConstantsArePublicStringsWithoutBindPlaceholders(): void
    {
        $constants = (new \ReflectionClass(TeamAwardSql::class))->getReflectionConstants();

        self::assertCount(3, $constants);
        foreach ($constants as $constant) {
            $name = $constant->getName();
            self::assertTrue($constant->isPublic(), $name . ' must be public');
            self::assertSame('string', (string) $constant->getType(), $name . ' must be typed string');
            $value = $constant->getValue();
            self::assertIsString($value);
            self::assertStringNotContainsString('?', $value, $name . ' must carry no ? bind placeholder');
        }
    }
}
