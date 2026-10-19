<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeRosterPreviewCashRowBuilder;

/**
 * Pins Trading\TradeRosterPreviewCashRowBuilder as state-free: its only
 * property is the injected validator, and no method parameter is typed
 * \mysqli. Escaping of team names stays inside buildCashRows(); the builder
 * holds no database handle and no actor identity.
 */
final class TradeRosterPreviewCashRowBuilderStateFreeTest extends TestCase
{
    public function testDeclaresOnlyTheValidatorProperty(): void
    {
        self::assertSame(['validator'], $this->declaredPropertyNames(TradeRosterPreviewCashRowBuilder::class));

        $constructor = (new \ReflectionClass(TradeRosterPreviewCashRowBuilder::class))->getConstructor();
        self::assertNotNull($constructor);
        self::assertCount(1, $constructor->getParameters());

        $type = $constructor->getParameters()[0]->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(\Trading\Contracts\TradeRosterPreviewParamValidatorInterface::class, $type->getName());
    }

    public function testNoMethodParameterIsTypedMysqli(): void
    {
        self::assertSame([], $this->mysqliTypedParameters(TradeRosterPreviewCashRowBuilder::class));
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
        self::assertSame(['__construct::db','run::conn'], $this->mysqliTypedParameters($fixture));
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
