<?php

declare(strict_types=1);

namespace Tests\Security;

use Api\Repository\ApiGameRepository;
use Api\Repository\ApiPlayerRepository;
use Api\Repository\ApiTeamRepository;
use BasketballStats\TeamStatsCalculator;
use Boxscore\Boxscore;
use JsbParser\PlayerIdResolver;
use League\LeagueContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Search\SearchRepository;
use SeasonLeaderboards\SeasonLeaderboardsRepository;
use Standings\StandingsRepository;
use Standings\StandingsUpdaterRepository;
use Team\TeamQueryRepository;
use TeamOffDefStats\TeamOffDefStatsRepository;
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
    private const TABLE_PATTERN = '/^ibl_[a-z_]+$/';
    private const BACKTICKED_COLUMN_PATTERN = '/^`[a-z_]+`$/';
    private const SORT_EXPRESSION_PATTERN = '/^[()`a-z_*+\-\/0-9]+$/';
    private const VOTE_COLUMN_PATTERN = '/^[a-z0-9_]+$/';
    private const PREFIX_PATTERN = '/^[a-z]+$/';
    /** Captures the path of every ibl.sqlStringConcatenation entry in a PHPStan baseline. */
    private const SQL_CONCAT_ENTRY_PATTERN = '/identifier: ibl\.sqlStringConcatenation\s*\n\s*count: \d+\s*\n\s*path: (\S+)/';

    /**
     * Every class whose SQL identifier construction was converted under
     * BanSqlStringConcatenationRule, repositories and non-repositories alike. The
     * method name predates the non-repositories; kept so data-set names stay stable.
     *
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
            'TeamQueryRepository' => [TeamQueryRepository::class],
            'TeamStatsCalculator' => [TeamStatsCalculator::class],
            'PlayerIdResolver' => [PlayerIdResolver::class],
            'Boxscore' => [Boxscore::class],
            'SearchRepository' => [SearchRepository::class],
            'TeamOffDefStatsRepository' => [TeamOffDefStatsRepository::class],
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
            'VotingRepository::ASG_COLUMNS' => [VotingRepository::class, 'ASG_COLUMNS', self::VOTE_COLUMN_PATTERN, false, true],
            'VotingRepository::EOY_COLUMNS' => [VotingRepository::class, 'EOY_COLUMNS', self::VOTE_COLUMN_PATTERN, false, true],
            'TeamQueryRepository::LAST_SIM_DEPTH_COLUMNS' => [TeamQueryRepository::class, 'LAST_SIM_DEPTH_COLUMNS', self::COLUMN_PATTERN, false, false],
            'TeamQueryRepository::DEPTH_CHART_DEPTH_COLUMNS' => [TeamQueryRepository::class, 'DEPTH_CHART_DEPTH_COLUMNS', self::COLUMN_PATTERN, false, false],
            'TeamStatsCalculator::STANDINGS_TABLES' => [TeamStatsCalculator::class, 'STANDINGS_TABLES', self::TABLE_PATTERN, false, false],
            'PlayerIdResolver::PLR_TABLES' => [PlayerIdResolver::class, 'PLR_TABLES', self::TABLE_PATTERN, false, false],
            'PlayerIdResolver::SNAPSHOT_TABLES' => [PlayerIdResolver::class, 'SNAPSHOT_TABLES', self::TABLE_PATTERN, false, false],
            'SearchRepository::SUPPORTED_PREFIXES' => [SearchRepository::class, 'SUPPORTED_PREFIXES', self::PREFIX_PATTERN, false, true],
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
        self::assertNotSame([], $map);

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

        self::assertNotSame([], $violations);
        $endsWithLoggedInTeamId = array_filter(
            $violations,
            static fn (string $violation): bool => str_ends_with($violation, '$loggedInTeamID')
                || str_ends_with($violation, '$loggedInTeamID)'),
        );
        self::assertNotSame([], $endsWithLoggedInTeamId);
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

    /**
     * LeagueContext selects a league table set and is not actor identity. Neither regex
     * matches it today by spelling alone, so this pins the classification.
     */
    public function testLeagueContextCollaboratorIsNotClassifiedAsActorIdentity(): void
    {
        foreach ([TeamStatsCalculator::class, PlayerIdResolver::class] as $class) {
            $parameter = new \ReflectionParameter([$class, '__construct'], 'leagueContext');
            $type = $parameter->getType();

            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            self::assertSame(LeagueContext::class, $type->getName());
            self::assertTrue($type->allowsNull(), "{$class}: leagueContext stays optional.");
            self::assertFalse(
                self::isActorIdentity('leagueContext', $type),
                "{$class}: LeagueContext selects a league table set and is not actor identity. Re-classify deliberately if the regexes change."
            );
        }
    }

    public function testTableShapeCheckFlagsInjectedTableValue(): void
    {
        foreach (
            [
                ['ibl' => 'ibl_plr; DROP TABLE ibl_plr'],
                ['ibl' => 'users'],
                ['ibl' => '`ibl_plr`'],
            ] as $map
        ) {
            self::assertNotSame([], self::nonIdentifierValues($map, self::TABLE_PATTERN, false));
        }

        self::assertSame(
            [],
            self::nonIdentifierValues(['ibl' => 'ibl_olympics_plr_snapshots'], self::TABLE_PATTERN, false)
        );
    }

    public function testPrefixShapeCheckFlagsInjectedPrefixValue(): void
    {
        $map = ['nuke', 'nuke_topics; DROP TABLE nuke_topics; --'];
        self::assertSame(
            ['nuke_topics; DROP TABLE nuke_topics; --'],
            self::nonIdentifierValues($map, self::PREFIX_PATTERN, true),
        );

        // A hyphenated prefix is outside the shape, so adding one to SUPPORTED_PREFIXES fails.
        self::assertSame(['nuke-prod'], self::nonIdentifierValues(['nuke', 'nuke-prod'], self::PREFIX_PATTERN, true));

        // A trailing underscore is not a prefix; the "_stories" suffix supplies the separator.
        self::assertNotSame([], self::nonIdentifierValues(['nuke_'], self::PREFIX_PATTERN, true));
    }

    public function testSqlConcatenationBaselineIsEmpty(): void
    {
        $neon = file_get_contents(__DIR__ . '/../../phpstan-baseline.neon');
        self::assertIsString($neon);

        $matched = preg_match_all(self::SQL_CONCAT_ENTRY_PATTERN, $neon, $matches);
        self::assertNotFalse($matched);

        self::assertSame([], $matches[1], 'ibl.sqlStringConcatenation entries reappeared in phpstan-baseline.neon: ' . implode(', ', $matches[1]));
    }

    public function testSqlConcatBaselineRegexDetectsSampleEntry(): void
    {
        // Positive control: without it, a regex that stopped matching the baseline format
        // would leave testSqlConcatenationBaselineIsEmpty vacuously green.
        $sample = "\t\t-\n"
            . "\t\t\trawMessage: 'SQL string concatenates a non-constant value.'\n"
            . "\t\t\tidentifier: ibl.sqlStringConcatenation\n"
            . "\t\t\tcount: 1\n"
            . "\t\t\tpath: classes/Foo/FooRepository.php\n";

        $matched = preg_match_all(self::SQL_CONCAT_ENTRY_PATTERN, $sample, $matches);

        self::assertSame(1, $matched);
        self::assertSame(['classes/Foo/FooRepository.php'], $matches[1]);
    }

    public function testNoSqlTextBuiltWithStringFormattingFunctions(): void
    {
        // BanSqlStringConcatenationRule only visits `.` concatenation, so SQL assembled by
        // sprintf()/strtr() would slip past it with the baseline still empty. The two files
        // below predate the rule; their counts may shrink but never grow.
        $allowed = [
            'Boxscore/BoxscoreAuditRepository.php' => 1,
            'HeadToHeadRecords/HeadToHeadRecordsRepository.php' => 5,
        ];
        $classesDir = realpath(__DIR__ . '/../../classes');
        self::assertIsString($classesDir);

        $excess = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($classesDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            // phpunit-hygiene-allow: the property is source shape; no runtime seam shows how SQL text was assembled
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            $relative = substr($file->getPathname(), strlen($classesDir) + 1);
            $count = count(self::formattedSqlCalls($source));
            if ($count > ($allowed[$relative] ?? 0)) {
                $excess[] = $relative . ' (' . $count . ')';
            }
        }

        self::assertSame([], $excess, 'SQL text built with sprintf/strtr: ' . implode(', ', $excess));
    }

    public function testFormattedSqlScanDetectsSampleCalls(): void
    {
        // Positive control: keeps testNoSqlTextBuiltWithStringFormattingFunctions from passing vacuously.
        $source = "\$a = strtr('SELECT * FROM t WHERE x = :x', \$map);\n"
            . "\$b = sprintf(\"UPDATE t SET y = %d\", \$y);\n"
            . "\$c = sprintf('%d players', \$n);\n";

        self::assertSame(["strtr('SELECT", 'sprintf("UPDATE'], self::formattedSqlCalls($source));
    }

    /** @return list<string> */
    private static function formattedSqlCalls(string $source): array
    {
        preg_match_all('/\b(?:sprintf|strtr)\s*\(\s*[\'"]\s*(?:SELECT|INSERT|UPDATE|DELETE|REPLACE)\b/i', $source, $matches);

        return $matches[0];
    }

    public function testColumnShapeCheckFlagsInjectedDepthColumnValue(): void
    {
        $map = (new \ReflectionClassConstant(TeamQueryRepository::class, 'LAST_SIM_DEPTH_COLUMNS'))->getValue();
        self::assertIsArray($map);
        self::assertSame([], self::nonIdentifierValues($map, self::COLUMN_PATTERN, false));

        // Mutation: LAST_SIM_DEPTH_COLUMNS['PG'] set to a free-text value.
        $map['PG'] = 'pg_depth = 1 OR 1';
        self::assertSame(['pg_depth = 1 OR 1'], self::nonIdentifierValues($map, self::COLUMN_PATTERN, false));
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
