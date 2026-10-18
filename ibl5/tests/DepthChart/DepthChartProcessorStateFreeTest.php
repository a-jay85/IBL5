<?php

declare(strict_types=1);

namespace Tests\DepthChart;

use DepthChart\DepthChartProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Pins DepthChart\DepthChartProcessor as state-free: no constructor, no
 * properties, and no method parameter typed \mysqli. Roster ownership and
 * CSRF stay in DepthChartSubmissionHandler / the module entry point.
 */
final class DepthChartProcessorStateFreeTest extends TestCase
{
    public function testDeclaresNoConstructorAndNoProperties(): void
    {
        $class = new \ReflectionClass(DepthChartProcessor::class);

        self::assertNull($class->getConstructor());
        self::assertSame([], $this->declaredPropertyNames(DepthChartProcessor::class));
    }

    public function testNoMethodParameterIsTypedMysqli(): void
    {
        self::assertSame([], $this->mysqliTypedParameters(DepthChartProcessor::class));
    }

    public function testProbeFlagsIntroducedStateAndMysqliParameter(): void
    {
        $fixture = new class (null) {
            public function __construct(public ?\mysqli $db)
            {
            }

            public function run(\mysqli $conn): void
            {
            }
        };

        self::assertSame(['db'], $this->declaredPropertyNames($fixture));
        self::assertSame(['__construct::db', 'run::conn'], $this->mysqliTypedParameters($fixture));
        self::assertNotNull((new \ReflectionClass($fixture))->getConstructor());
    }

    /**
     * @param class-string|object $class
     * @return list<string>
     */
    private function declaredPropertyNames(string|object $class): array
    {
        $names = [];
        foreach ((new \ReflectionClass($class))->getProperties() as $property) {
            $names[] = $property->getName();
        }

        return $names;
    }

    /**
     * @param class-string|object $class
     * @return list<string> "method::param" for every parameter whose named type is mysqli
     */
    private function mysqliTypedParameters(string|object $class): array
    {
        $hits = [];
        foreach ((new \ReflectionClass($class))->getMethods() as $method) {
            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof \ReflectionNamedType && ltrim($type->getName(), '\\') === 'mysqli') {
                    $hits[] = $method->getName() . '::' . $parameter->getName();
                }
            }
        }

        return $hits;
    }
}
