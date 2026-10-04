<?php

declare(strict_types=1);

namespace Tests\League;

use PHPUnit\Framework\TestCase;

final class RootClassNamespaceTest extends TestCase
{
    private const CLASSES_DIR = __DIR__ . '/../../classes';

    public function testDatabaseBaseMysqliRepositoryResolves(): void
    {
        $ref = new \ReflectionClass(\Database\BaseMysqliRepository::class);
        self::assertTrue($ref->isAbstract());
        self::assertSame('Database', $ref->getNamespaceName());
        self::assertTrue($ref->hasMethod('transactional'));
        self::assertStringEndsWith('/classes/Database/BaseMysqliRepository.php', (string) $ref->getFileName());
    }

    public function testLeagueJsbResolvesWithConstants(): void
    {
        $ref = new \ReflectionClass(\League\JSB::class);
        self::assertSame('League', $ref->getNamespaceName());
        foreach (['PLAYER_POSITIONS', 'PLAYOFF_MONTH', 'WAIVERS_ORDINAL'] as $constant) {
            self::assertTrue($ref->hasConstant($constant), "League\\JSB lacks {$constant}");
        }
        self::assertStringEndsWith('/classes/League/JSB.php', (string) $ref->getFileName());
    }

    public function testLeagueContractRulesResolves(): void
    {
        $ref = new \ReflectionClass(\League\ContractRules::class);
        self::assertSame('League', $ref->getNamespaceName());
        self::assertStringEndsWith('/classes/League/ContractRules.php', (string) $ref->getFileName());
    }

    public function testOldGlobalNamesDoNotResolve(): void
    {
        foreach (['BaseMysqliRepository', 'JSB', 'ContractRules'] as $oldName) {
            self::assertFalse(class_exists($oldName), "global class {$oldName} still resolves");
        }
    }

    public function testNoUnexpectedRootLevelClassFiles(): void
    {
        $files = glob(self::CLASSES_DIR . '/*.php');
        self::assertIsArray($files);
        $names = array_map('basename', $files);
        sort($names);
        self::assertSame(['TeamOrderBy.php'], $names);
    }
}
