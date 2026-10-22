<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Team color and owner-name lookups live in TeamIdentityRepository. No class
 * on the caller path may hold a mysqli-typed member or issue raw SQL.
 * Card views and CardBaseStyles receive a prebuilt color scheme and may not
 * hold a TeamIdentityRepositoryInterface-typed member either.
 */
final class TeamLookupSqlLocalityTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string}>
     */
    public static function teamLookupCallerClasses(): array
    {
        return [
            'TeamColorHelper' => [\Player\Views\TeamColorHelper::class],
            'CardBaseStyles' => [\Player\Views\CardBaseStyles::class],
            'PlayerTradingCardFrontView' => [\Player\Views\PlayerTradingCardFrontView::class],
            'PlayerTradingCardBackView' => [\Player\Views\PlayerTradingCardBackView::class],
            'PlayerTradingCardFlipView' => [\Player\Views\PlayerTradingCardFlipView::class],
            'PlayerStatsFlipCardView' => [\Player\Views\PlayerStatsFlipCardView::class],
            'HeadToHeadRecordsController' => [\HeadToHeadRecords\HeadToHeadRecordsController::class],
        ];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('teamLookupCallerClasses')]
    public function testTeamLookupCallersHoldNoMysqliSurface(string $class): void
    {
        $ref = new \ReflectionClass($class);

        self::assertSame([], self::membersTypedAs($ref, 'mysqli'), $class);
        self::assertSame([], self::rawSqlCalls((string) file_get_contents((string) $ref->getFileName())), $class);
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function colorSchemeConsumerClasses(): array
    {
        return [
            'TeamColorHelper' => [\Player\Views\TeamColorHelper::class],
            'CardBaseStyles' => [\Player\Views\CardBaseStyles::class],
            'PlayerTradingCardFrontView' => [\Player\Views\PlayerTradingCardFrontView::class],
            'PlayerTradingCardBackView' => [\Player\Views\PlayerTradingCardBackView::class],
            'PlayerTradingCardFlipView' => [\Player\Views\PlayerTradingCardFlipView::class],
            'PlayerStatsFlipCardView' => [\Player\Views\PlayerStatsFlipCardView::class],
        ];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('colorSchemeConsumerClasses')]
    public function testColorSchemeConsumersHoldNoTeamIdentityRepository(string $class): void
    {
        self::assertSame(
            [],
            self::membersTypedAs(new \ReflectionClass($class), 'TeamIdentityRepositoryInterface'),
            $class,
        );
    }

    public function testDetectorFlagsTeamIdentityRepositoryTypedParameter(): void
    {
        $subject = new class {
            public function render(?\Repositories\Contracts\TeamIdentityRepositoryInterface $teamRepo = null): void
            {
            }
        };

        self::assertSame(
            ['render($teamRepo)'],
            self::membersTypedAs(new \ReflectionClass($subject), 'TeamIdentityRepositoryInterface'),
        );
    }

    public function testDetectorFlagsTeamIdentityRepositoryTypedProperty(): void
    {
        $subject = new class {
            public ?\Repositories\Contracts\TeamIdentityRepositoryInterface $teamRepo = null;
        };

        self::assertSame(
            ['$teamRepo'],
            self::membersTypedAs(new \ReflectionClass($subject), 'TeamIdentityRepositoryInterface'),
        );
    }

    public function testDetectorIgnoresColorSchemeArrayParameter(): void
    {
        $subject = new class {
            /**
             * @param array<string, string>|null $colorScheme
             */
            public function render(?array $colorScheme = null): void
            {
            }
        };

        self::assertSame([], self::membersTypedAs(new \ReflectionClass($subject), 'TeamIdentityRepositoryInterface'));
    }

    public function testDetectorFlagsMysqliTypedParameter(): void
    {
        $subject = new class {
            public function lookup(?\mysqli $db): void
            {
            }
        };

        self::assertSame(['lookup($db)'], self::membersTypedAs(new \ReflectionClass($subject), 'mysqli'));
    }

    public function testDetectorFlagsMysqliTypedProperty(): void
    {
        $subject = new class {
            public ?\mysqli $db = null;
        };

        self::assertSame(['$db'], self::membersTypedAs(new \ReflectionClass($subject), 'mysqli'));
    }

    public function testDetectorFlagsRawPrepareAndQueryCalls(): void
    {
        self::assertCount(2, self::rawSqlCalls('$stmt = $db->prepare("SELECT 1"); $db->query("SELECT 1");'));
    }

    public function testDetectorIgnoresRepositoryMethodCalls(): void
    {
        self::assertSame([], self::rawSqlCalls('$this->teamRepo->getOwnerName($id); $repo->queryRecords();'));
    }

    /**
     * Members declared on $ref whose parameter or property type mentions $typeNeedle.
     *
     * @template T of object
     * @param \ReflectionClass<T> $ref
     * @param string $typeNeedle
     * @return list<string>
     */
    private static function membersTypedAs(\ReflectionClass $ref, string $typeNeedle): array
    {
        $hits = [];
        foreach ($ref->getProperties() as $prop) {
            if ($prop->getDeclaringClass()->getName() === $ref->getName()
                && stripos((string) $prop->getType(), $typeNeedle) !== false) {
                $hits[] = '$' . $prop->getName();
            }
        }
        foreach ($ref->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $ref->getName()) {
                continue;
            }
            foreach ($method->getParameters() as $param) {
                if (stripos((string) $param->getType(), $typeNeedle) !== false) {
                    $hits[] = $method->getName() . '($' . $param->getName() . ')';
                }
            }
        }
        return $hits;
    }

    /** @return list<string> */
    private static function rawSqlCalls(string $source): array
    {
        preg_match_all('/->(prepare|query|real_query|multi_query)\s*\(/i', $source, $m);
        return $m[0];
    }
}
