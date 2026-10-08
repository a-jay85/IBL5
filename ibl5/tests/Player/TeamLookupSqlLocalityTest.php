<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Team color and owner-name lookups live in TeamIdentityRepository. No class
 * on the caller path may hold a mysqli-typed member or issue raw SQL.
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

        self::assertSame([], self::mysqliTypedMembers($ref), $class);
        self::assertSame([], self::rawSqlCalls((string) file_get_contents((string) $ref->getFileName())), $class);
    }

    public function testDetectorFlagsMysqliTypedParameter(): void
    {
        $subject = new class {
            public function lookup(?\mysqli $db): void
            {
            }
        };

        self::assertSame(['lookup($db)'], self::mysqliTypedMembers(new \ReflectionClass($subject)));
    }

    public function testDetectorFlagsMysqliTypedProperty(): void
    {
        $subject = new class {
            public ?\mysqli $db = null;
        };

        self::assertSame(['$db'], self::mysqliTypedMembers(new \ReflectionClass($subject)));
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
     * Members declared on $ref whose parameter or property type mentions mysqli.
     *
     * @template T of object
     * @param \ReflectionClass<T> $ref
     * @return list<string>
     */
    private static function mysqliTypedMembers(\ReflectionClass $ref): array
    {
        $hits = [];
        foreach ($ref->getProperties() as $prop) {
            if ($prop->getDeclaringClass()->getName() === $ref->getName()
                && stripos((string) $prop->getType(), 'mysqli') !== false) {
                $hits[] = '$' . $prop->getName();
            }
        }
        foreach ($ref->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $ref->getName()) {
                continue;
            }
            foreach ($method->getParameters() as $param) {
                if (stripos((string) $param->getType(), 'mysqli') !== false) {
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
