<?php

declare(strict_types=1);

namespace Tests\League;

use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testJsbConstantValuesArePinned(): void
    {
        // @phpstan-ignore staticMethod.alreadyNarrowedType (const value statically known; assertion guards future edits)
        self::assertSame(['PG', 'SG', 'SF', 'PF', 'C'], \League\JsbConstants::PLAYER_POSITIONS);
        // @phpstan-ignore staticMethod.alreadyNarrowedType (const value statically known; assertion guards future edits)
        self::assertSame(22, \League\JsbConstants::PLAYOFF_MONTH);
        // @phpstan-ignore staticMethod.alreadyNarrowedType (const value statically known; assertion guards future edits)
        self::assertSame(960, \League\JsbConstants::WAIVERS_ORDINAL);
    }

    public function testLeagueContractRulesResolves(): void
    {
        $ref = new \ReflectionClass(\League\ContractRules::class);
        self::assertSame('League', $ref->getNamespaceName());
        self::assertStringEndsWith('/classes/League/ContractRules.php', (string) $ref->getFileName());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function oldGlobalNameProvider(): array
    {
        return [
            'BaseMysqliRepository' => ['BaseMysqliRepository'],
            'JSB' => ['JSB'],
            'ContractRules' => ['ContractRules'],
        ];
    }

    #[DataProvider('oldGlobalNameProvider')]
    public function testOldGlobalNameDoesNotResolve(string $oldName): void
    {
        self::assertFalse(class_exists($oldName), "global class {$oldName} still resolves");
    }

    public function testOldLeagueJsbNameDoesNotResolve(): void
    {
        self::assertFalse(class_exists('League\\JSB'), 'League\\JSB still resolves; rename to League\\JsbConstants is incomplete');
        self::assertFileDoesNotExist(self::CLASSES_DIR . '/League/JSB.php');
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
