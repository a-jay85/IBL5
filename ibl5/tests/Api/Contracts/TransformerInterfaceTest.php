<?php

declare(strict_types=1);

namespace Tests\Api\Contracts;

use Api\Contracts\TransformerInterface;
use Api\Transformer\BoxscoreTransformer;
use Api\Transformer\GameTransformer;
use Api\Transformer\InjuryTransformer;
use Api\Transformer\LeaderTransformer;
use Api\Transformer\PlayerExportTransformer;
use Api\Transformer\PlayerStatsTransformer;
use Api\Transformer\PlayerTransformer;
use Api\Transformer\StandingsTransformer;
use Api\Transformer\TeamTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

class TransformerInterfaceTest extends TestCase
{
    #[DataProvider('uniformTransformerProvider')]
    public function testUniformTransformersImplementInterface(string $class): void
    {
        $this->assertInstanceOf(TransformerInterface::class, new $class());
    }

    /** @return array<string, array{string}> */
    public static function uniformTransformerProvider(): array
    {
        return [
            GameTransformer::class => [GameTransformer::class],
            InjuryTransformer::class => [InjuryTransformer::class],
            LeaderTransformer::class => [LeaderTransformer::class],
            PlayerExportTransformer::class => [PlayerExportTransformer::class],
            PlayerTransformer::class => [PlayerTransformer::class],
            StandingsTransformer::class => [StandingsTransformer::class],
            TeamTransformer::class => [TeamTransformer::class],
        ];
    }

    /**
     * BoxscoreTransformer is intentionally excluded (divergent method signatures).
     * This guards against a future blanket implements that would be a lie about its contract.
     */
    public function testBoxscoreTransformerNotTransformerInterface(): void
    {
        $interfaces = class_implements(BoxscoreTransformer::class);
        $this->assertIsArray($interfaces);
        $this->assertArrayNotHasKey(TransformerInterface::class, $interfaces);
    }

    /**
     * PlayerStatsTransformer is intentionally excluded (divergent method signatures).
     */
    public function testPlayerStatsTransformerNotTransformerInterface(): void
    {
        $interfaces = class_implements(PlayerStatsTransformer::class);
        $this->assertIsArray($interfaces);
        $this->assertArrayNotHasKey(TransformerInterface::class, $interfaces);
    }

    public function testInterfaceDeclaresTransformWithArrayParamAndReturn(): void
    {
        $method = new ReflectionMethod(TransformerInterface::class, 'transform');

        $params = $method->getParameters();
        $this->assertCount(1, $params, 'transform() must have exactly one parameter');

        $paramType = $params[0]->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $paramType);
        $this->assertSame('array', $paramType->getName());

        $returnType = $method->getReturnType();
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        $this->assertSame('array', $returnType->getName());
    }
}
