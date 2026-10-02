<?php

declare(strict_types=1);

namespace Tests\Security;

use Api\Repository\ApiGameRepository;
use Api\Repository\ApiPlayerRepository;
use Api\Repository\ApiTeamRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SeasonLeaderboards\SeasonLeaderboardsRepository;
use Standings\StandingsRepository;
use Standings\StandingsUpdaterRepository;
use Voting\VotingRepository;

/**
 * Structural invariant behind the SQL identifier allowlist conversion (backlog #232).
 *
 * PHPStan's BanSqlStringConcatenationRule proves each converted ORDER BY / column
 * operand is a constant-string union. This test covers what PHPStan cannot see:
 * none of the converted repositories carries actor identity, and every identifier
 * map they splice into query text holds only identifier-shaped literals.
 */
final class SqlIdentifierAllowlistInvariantTest extends TestCase
{
    private const ACTOR_IDENTITY_NAME = '/(loggedin|session|currentuser|authuser|userid|username|cookie|apikey|request)/i';
    private const ACTOR_IDENTITY_TYPE = '/(Auth|Session|CurrentUser|Request|ApiKey)/';

    private const COLUMN_PATTERN = '/^[a-z_]+$/';
    private const BACKTICKED_COLUMN_PATTERN = '/^`[a-z_]+`$/';
    private const SORT_EXPRESSION_PATTERN = '/^[()`a-z_*+\-\/0-9]+$/';
    private const VOTE_COLUMN_PATTERN = '/^[a-z0-9_]+$/';

    /**
     * @return array<string, array{class-string}>
     */
    public static function convertedRepositoryProvider(): array
    {
        return [
            'VotingRepository' => [VotingRepository::class],
            'StandingsUpdaterRepository' => [StandingsUpdaterRepository::class],
            'StandingsRepository' => [StandingsRepository::class],
            'SeasonLeaderboardsRepository' => [SeasonLeaderboardsRepository::class],
            'ApiGameRepository' => [ApiGameRepository::class],
            'ApiPlayerRepository' => [ApiPlayerRepository::class],
            'ApiTeamRepository' => [ApiTeamRepository::class],
        ];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('convertedRepositoryProvider')]
    public function testConvertedRepositoriesCarryNoActorIdentity(string $class): void
    {
        self::assertSame([], self::actorIdentityViolations($class));
    }

    /**
     * @return array<string, array{class-string, string, string, bool, bool}>
     *         [class, const, value pattern, value must equal key, list keys allowed]
     */
    public static function identifierMapProvider(): array
    {
        return [
            'ApiPlayerRepository::SORT_COLUMNS' => [ApiPlayerRepository::class, 'SORT_COLUMNS', self::COLUMN_PATTERN, true, false],
            'ApiTeamRepository::SORT_COLUMNS' => [ApiTeamRepository::class, 'SORT_COLUMNS', self::COLUMN_PATTERN, true, false],
            'ApiGameRepository::SORT_COLUMNS' => [ApiGameRepository::class, 'SORT_COLUMNS', self::COLUMN_PATTERN, true, false],
            'StandingsUpdaterRepository::GROUPING_COLUMN_SQL' => [StandingsUpdaterRepository::class, 'GROUPING_COLUMN_SQL', self::BACKTICKED_COLUMN_PATTERN, false, false],
            'StandingsUpdaterRepository::MAGIC_NUMBER_COLUMN_SQL' => [StandingsUpdaterRepository::class, 'MAGIC_NUMBER_COLUMN_SQL', self::BACKTICKED_COLUMN_PATTERN, false, false],
            'StandingsUpdaterRepository::CLINCHED_COLUMN_SQL' => [StandingsUpdaterRepository::class, 'CLINCHED_COLUMN_SQL', self::BACKTICKED_COLUMN_PATTERN, false, false],
            'SeasonLeaderboardsRepository::SORT_EXPRESSIONS' => [SeasonLeaderboardsRepository::class, 'SORT_EXPRESSIONS', self::SORT_EXPRESSION_PATTERN, false, false],
            'VotingRepository::ALLOWED_COLUMNS' => [VotingRepository::class, 'ALLOWED_COLUMNS', self::VOTE_COLUMN_PATTERN, false, true],
        ];
    }

    /**
     * Catches a free-text value slipped into a map, e.g. setting
     * ApiGameRepository::SORT_COLUMNS['game_date'] to 'game_date DESC'.
     *
     * @param class-string $class
     */
    #[DataProvider('identifierMapProvider')]
    public function testIdentifierMapsHoldOnlyIdentifierShapedLiterals(
        string $class,
        string $const,
        string $pattern,
        bool $valueEqualsKey,
        bool $allowListKeys,
    ): void {
        $map = (new \ReflectionClassConstant($class, $const))->getValue();
        self::assertIsArray($map);
        self::assertNotEmpty($map);

        self::assertSame([], self::nonIdentifierValues($map, $pattern, $allowListKeys));

        foreach ($map as $key => $value) {
            self::assertIsString($value);
            self::assertStringNotContainsString('--', $value);
            if ($valueEqualsKey) {
                self::assertSame($key, $value, "{$class}::{$const} value must equal its key");
            }
        }
    }

    public function testActorIdentityCheckFlagsFixtureWithLoggedInTeamId(): void
    {
        $fixture = new class (0) {
            public function __construct(private int $loggedInTeamID)
            {
            }

            public function teamId(): int
            {
                return $this->loggedInTeamID;
            }
        };

        $violations = self::actorIdentityViolations($fixture::class);

        self::assertNotEmpty($violations);
        $endsWithLoggedInTeamId = array_filter(
            $violations,
            static fn (string $violation): bool => str_ends_with($violation, '$loggedInTeamID')
                || str_ends_with($violation, '$loggedInTeamID)'),
        );
        self::assertNotEmpty($endsWithLoggedInTeamId);
    }

    public function testIdentifierShapeCheckFlagsInjectedMapValue(): void
    {
        self::assertSame(
            ['age; DROP TABLE ibl_plr'],
            self::nonIdentifierValues(['name' => 'name', 'age' => 'age; DROP TABLE ibl_plr'], self::COLUMN_PATTERN, false),
        );

        self::assertSame(
            ['(`pts`) -- x'],
            self::nonIdentifierValues(['PTS' => '(`pts`) -- x'], self::SORT_EXPRESSION_PATTERN, false),
        );
    }

    /**
     * @param class-string $class
     * @return list<string> violations as "Class::$name" or "Class::__construct($name)"
     */
    private static function actorIdentityViolations(string $class): array
    {
        $violations = [];
        $reflection = new \ReflectionClass($class);

        // getProperties() omits parent privates, so walk the hierarchy explicitly.
        for ($current = $reflection; $current !== false; $current = $current->getParentClass()) {
            foreach ($current->getProperties() as $property) {
                if (self::isActorIdentity($property->getName(), $property->getType())) {
                    $violations[] = $current->getName() . '::$' . $property->getName();
                }
            }
        }

        $constructor = $reflection->getConstructor();
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                if (self::isActorIdentity($parameter->getName(), $parameter->getType())) {
                    $violations[] = $class . '::__construct($' . $parameter->getName() . ')';
                }
            }
        }

        return array_values(array_unique($violations));
    }

    private static function isActorIdentity(string $name, ?\ReflectionType $type): bool
    {
        if (preg_match(self::ACTOR_IDENTITY_NAME, $name) === 1) {
            return true;
        }

        $namedTypes = match (true) {
            $type instanceof \ReflectionNamedType => [$type],
            $type instanceof \ReflectionUnionType, $type instanceof \ReflectionIntersectionType => $type->getTypes(),
            default => [],
        };

        foreach ($namedTypes as $namedType) {
            if (!$namedType instanceof \ReflectionNamedType || $namedType->isBuiltin()) {
                continue;
            }
            $parts = explode('\\', $namedType->getName());
            if (preg_match(self::ACTOR_IDENTITY_TYPE, end($parts)) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $map
     * @return list<string> offending values (non-string keys/values are reported via var_export)
     */
    private static function nonIdentifierValues(array $map, string $pattern, bool $allowListKeys): array
    {
        $offending = [];
        foreach ($map as $key => $value) {
            if (is_int($key) && !$allowListKeys) {
                $offending[] = 'key ' . var_export($key, true);
                continue;
            }
            if (!is_string($value)) {
                $offending[] = var_export($value, true);
                continue;
            }
            if (preg_match($pattern, $value) !== 1 || str_contains($value, '--')) {
                $offending[] = $value;
            }
        }

        return $offending;
    }
}
