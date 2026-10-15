<?php

declare(strict_types=1);

namespace Tests\Unit\SimRecap;

use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use SimRecap\MentionMapBuilder;

/**
 * Pins SimRecap\MentionMapBuilder as state-free. The CLI guard, the ssh gate, and
 * the scripts/.htaccess denial stay in scripts/simRecapQueue.php and remain pinned
 * by SimRecapQueueGuardTest.
 */
final class MentionMapBuilderStateFreeTest extends TestCase
{
    public function testConstructorTakesOnlyTeamIdentityContract(): void
    {
        self::assertSame(
            [TeamIdentityRepositoryInterface::class],
            $this->constructorParameterTypes(MentionMapBuilder::class)
        );

        $constructor = (new \ReflectionClass(MentionMapBuilder::class))->getConstructor();
        self::assertNotNull($constructor);
        $type = $constructor->getParameters()[0]->getType();
        self::assertNotNull($type);
        self::assertFalse($type->allowsNull());
    }

    public function testDeclaresNoPropertyBeyondTeamsCollaborator(): void
    {
        $names = [];
        foreach ((new \ReflectionClass(MentionMapBuilder::class))->getProperties() as $property) {
            $names[] = $property->getName();
            $type = $property->getType();
            if ($type instanceof \ReflectionNamedType) {
                self::assertNotSame('mysqli', ltrim($type->getName(), '\\'));
            }
        }

        self::assertSame(['teams'], $names);
    }

    public function testConstructorProbeFlagsMysqliParameter(): void
    {
        $fixture = new class (null) {
            public function __construct(public ?\mysqli $db)
            {
            }
        };

        $types = $this->constructorParameterTypes($fixture);

        self::assertContains('mysqli', $types);
        self::assertNotSame([TeamIdentityRepositoryInterface::class], $types);
    }

    /**
     * @param class-string|object $class
     * @return list<string>
     */
    private function constructorParameterTypes(string|object $class): array
    {
        $constructor = (new \ReflectionClass($class))->getConstructor();
        self::assertNotNull($constructor);

        $types = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            $types[] = ltrim($type->getName(), '\\');
        }

        return $types;
    }
}
