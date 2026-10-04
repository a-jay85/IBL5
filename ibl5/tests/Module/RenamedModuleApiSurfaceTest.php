<?php

declare(strict_types=1);

namespace Tests\Module;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the public surface of the PlayerSearch and SeasonRosterChanges modules to the
 * method lists the classes declared before the rename, so the rename added no entry point.
 */
final class RenamedModuleApiSurfaceTest extends TestCase
{
    /**
     * @return array<string, array{class-string, list<string>}>
     */
    public static function surfaceProvider(): array
    {
        return [
            'search repository' => [
                \PlayerSearch\PlayerSearchRepository::class,
                ['__construct', 'getPlayerById', 'searchPlayers'],
            ],
            'search service' => [
                \PlayerSearch\PlayerSearchService::class,
                ['__construct', 'search'],
            ],
            'search validator' => [
                \PlayerSearch\PlayerSearchValidator::class,
                [
                    'validateBooleanParam',
                    'validateIntegerParam',
                    'validatePosition',
                    'validateSearchParams',
                    'validateStringParam',
                ],
            ],
            'search view' => [
                \PlayerSearch\PlayerSearchView::class,
                ['renderPlayerRow', 'renderSearchForm', 'renderTableFooter', 'renderTableHeader'],
            ],
            'roster changes repository' => [
                \SeasonRosterChanges\SeasonRosterChangesRepository::class,
                ['getSeasonRosterChanges'],
            ],
            'roster changes view' => [
                \SeasonRosterChanges\SeasonRosterChangesView::class,
                ['render'],
            ],
        ];
    }

    /**
     * @return array<string, array{class-string, class-string}>
     */
    public static function contractProvider(): array
    {
        return [
            'search repository' => [
                \PlayerSearch\PlayerSearchRepository::class,
                \PlayerSearch\Contracts\PlayerSearchRepositoryInterface::class,
            ],
            'search service' => [
                \PlayerSearch\PlayerSearchService::class,
                \PlayerSearch\Contracts\PlayerSearchServiceInterface::class,
            ],
            'search validator' => [
                \PlayerSearch\PlayerSearchValidator::class,
                \PlayerSearch\Contracts\PlayerSearchValidatorInterface::class,
            ],
            'search view' => [
                \PlayerSearch\PlayerSearchView::class,
                \PlayerSearch\Contracts\PlayerSearchViewInterface::class,
            ],
            'roster changes repository' => [
                \SeasonRosterChanges\SeasonRosterChangesRepository::class,
                \SeasonRosterChanges\Contracts\SeasonRosterChangesRepositoryInterface::class,
            ],
            'roster changes view' => [
                \SeasonRosterChanges\SeasonRosterChangesView::class,
                \SeasonRosterChanges\Contracts\SeasonRosterChangesViewInterface::class,
            ],
        ];
    }

    /**
     * @param class-string $fqcn
     * @param list<string> $expected
     */
    #[DataProvider('surfaceProvider')]
    public function testDeclaredPublicMethodsMatchPreRenameSurface(string $fqcn, array $expected): void
    {
        $declared = [];
        foreach ((new \ReflectionClass($fqcn))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class === $fqcn) {
                $declared[] = $method->getName();
            }
        }
        sort($declared);
        sort($expected);

        self::assertSame($expected, $declared);
    }

    /**
     * @param class-string $fqcn
     * @param class-string $contract
     */
    #[DataProvider('contractProvider')]
    public function testRenamedClassesImplementTheirContracts(string $fqcn, string $contract): void
    {
        self::assertTrue((new \ReflectionClass($fqcn))->implementsInterface($contract));
    }

    public function testRetiredNamespacesNoLongerAutoload(): void
    {
        $classesDir = realpath(__DIR__ . '/../../classes');
        self::assertNotFalse($classesDir);

        $retired = [
            'Player' . 'Database\\PlayerDatabaseRepository',
            'Player' . 'Database\\PlayerDatabaseService',
            'Player' . 'Movement\\PlayerMovementRepository',
            'Player' . 'Movement\\Contracts\\PlayerMovementRepositoryInterface',
        ];

        foreach ($retired as $name) {
            // A worktree shares vendor/ with the main checkout, whose autoloader can still
            // resolve a retired name from its own tree. Only a hit inside this tree is a defect.
            if (class_exists($name) || interface_exists($name)) {
                $file = (new \ReflectionClass($name))->getFileName();
                self::assertFalse(
                    $file !== false && str_starts_with($file, $classesDir . '/'),
                    "Retired name '$name' still autoloads from this tree"
                );
            }
        }
    }
}
