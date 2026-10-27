<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use PHPUnit\Framework\TestCase;

class JsbImportRepositoryInterfaceTest extends TestCase
{
    public function testInterfaceDeclaresHasRetiredPlayersForYearTakingIntReturningBool(): void
    {
        $method = new \ReflectionMethod(JsbImportRepositoryInterface::class, 'hasRetiredPlayersForYear');

        self::assertTrue($method->isPublic());
        self::assertCount(1, $method->getParameters());

        $paramType = $method->getParameters()[0]->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $paramType);
        self::assertSame('int', $paramType->getName());

        $returnType = $method->getReturnType();
        self::assertInstanceOf(\ReflectionNamedType::class, $returnType);
        self::assertSame('bool', $returnType->getName());
    }
}
