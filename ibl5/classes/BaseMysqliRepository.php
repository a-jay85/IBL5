<?php

declare(strict_types=1);

use League\League;

/**
 * BaseMysqliRepository - Abstract base class for all IBL repositories using mysqli
 *
 * Provides standardized prepared statement execution with type validation,
 * error handling, and logging. All IBL repositories should extend this class.
 *
 * DESIGN PRINCIPLES:
 * - Stateless: Each method call is independent; statements are closed after use
 * - Type-safe: Parameter count and types are validated before binding
 * - Secure: All queries use prepared statements to prevent SQL injection
 * - Constructor injection: mysqli connection is passed via constructor
 *
 * ERROR CODES:
 * - 1001: Type/parameter count mismatch (strlen($types) !== count($params))
 * - 1002: Prepare failed (invalid SQL or connection issue)
 * - 1003: Execute failed (constraint violation, timeout, etc.)
 *
 * TYPE SPECIFICATION:
 * Use mysqli type characters in $types string:
 * - 'i' = integer (use for INT, SMALLINT, BIGINT, and DATE/DATETIME as Unix timestamps)
 * - 's' = string (use for VARCHAR, TEXT, CHAR)
 * - 'd' = double (use for FLOAT, DOUBLE, DECIMAL)
 * - 'b' = blob (use for BLOB, BINARY - rarely needed)
 *
 * NULL HANDLING:
 * mysqli's bind_param() does not have a NULL type. Calling code must:
 * - Build conditional WHERE clauses (e.g., "WHERE col IS NULL" vs "WHERE col = ?")
 * - Never pass null values to these methods; filter them before calling
 *
 * DATE/DATETIME HANDLING:
 * Bind DATE/DATETIME columns as Unix timestamps using 'i' type:
 *   $this->fetchOne("SELECT * FROM games WHERE date > FROM_UNIXTIME(?)", "i", time());
 *
 * LARGE RESULT SETS:
 * fetchAll() loads all rows into memory. For streaming large result sets:
 *   $stmt = $this->executeQuery($query, $types, ...$params);
 *   $result = $stmt->get_result();
 *   while ($row = $result->fetch_assoc()) { ... }
 *   $stmt->close();
 *
 * USAGE EXAMPLE:
 * ```php
 * class PlayerRepository extends BaseMysqliRepository
 * {
 *     public function getPlayerByName(string $name): ?array
 *     {
 *         return $this->fetchOne(
 *             "SELECT * FROM `ibl_plr` WHERE name = ? LIMIT 1",
 *             "s",
 *             $name
 *         );
 *     }
 *
 *     public function getPlayersByTeam(int $teamId, int $maxAge): array
 *     {
 *         return $this->fetchAll(
 *             "SELECT * FROM `ibl_plr` WHERE teamid = ? AND age <= ?",
 *             "ii",
 *             $teamId,
 *             $maxAge
 *         );
 *     }
 *
 *     public function updatePlayerTeam(int $pid, int $newTeamId): int
 *     {
 *         return $this->execute(
 *             "UPDATE `ibl_plr` SET teamid = ? WHERE pid = ?",
 *             "ii",
 *             $newTeamId,
 *             $pid
 *         );
 *     }
 * }
 * ```
 *
 * @see https://www.php.net/manual/en/mysqli-stmt.bind-param.php
 */
abstract class BaseMysqliRepository
{
    private static ?\League\LeagueContext $sharedLeagueContext = null;

    /**
     * @var \mysqli Database connection
     */
    protected \mysqli $db;

    /**
     * Optional league context for multi-league table resolution.
     * When set (or via the static shared context), rewriteTableNames() maps
     * backtick-quoted IBL table names to their league-specific equivalents.
     */
    protected ?\League\LeagueContext $leagueContext;

    /**
     * Optional PSR-3 logger for structured error logging.
     * When null, falls back to LoggerFactory::getChannel('db').
     */
    private ?\Psr\Log\LoggerInterface $logger = null;

    /**
     * Constructor with connection validation
     *
     * @param \mysqli $db Active mysqli connection
     * @param \League\LeagueContext|null $leagueContext Optional league context for table resolution
     * @throws \RuntimeException If connection is invalid or closed (error code 1002)
     */
    public function __construct(\mysqli $db, ?\League\LeagueContext $leagueContext = null)
    {
        if ($db->connect_errno !== 0) {
            $this->logError('Connection error in constructor', $db->connect_error ?? 'Unknown error');
            throw new \RuntimeException(
                'Invalid mysqli connection: ' . ($db->connect_error ?? 'Unknown error'),
                1002
            );
        }
        $this->db = $db;
        $this->leagueContext = $leagueContext;
    }

    /**
     * Set a PSR-3 logger for structured error logging.
     */
    public function setLogger(\Psr\Log\LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public static function setSharedLeagueContext(\League\LeagueContext $context): void
    {
        self::$sharedLeagueContext = $context;
    }

    public static function clearSharedLeagueContext(): void
    {
        self::$sharedLeagueContext = null;
    }

    /**
     * Rewrite IBL table names to their Olympics equivalents when the active
     * league context is Olympics.
     *
     * Opt-in is signalled by BACKTICK-QUOTING the table name (`` `ibl_plr` ``):
     * a query that quotes a mapped table is rewritten; a query that references
     * it bare (e.g. `FROM ibl_team_info`) is left on IBL. This is deliberate —
     * identity/GM lookups (e.g. NavigationRepository::resolveTeamId, which reads
     * the IBL-only `gm_username` column) write the table name bare precisely so
     * they stay on IBL even in Olympics context. Rewriting bare names would
     * route them to Olympics tables that lack those columns and fatal.
     *
     * When a table opts in, ALL of its references in that query are rewritten —
     * the backtick FROM, column-qualified refs (`ibl_plr.pid`), and any bare
     * mention. A naive backtick-only str_replace rewrote the FROM but left
     * `table.column` references dangling, producing "Unknown table" fatals
     * (e.g. Team::load() in Olympics context). Word boundaries ensure `ibl_plr`
     * never matches inside `ibl_plr_snapshots` or the already-rewritten
     * `ibl_olympics_plr`, so the transform is idempotent.
     *
     * The IBL→Olympics mapping is owned by {@see \League\LeagueContext::TABLE_MAP}
     * — the single source of truth shared with getTableName().
     */
    protected function rewriteTableNames(string $query): string
    {
        $ctx = $this->leagueContext ?? self::$sharedLeagueContext;
        if ($ctx === null || !$ctx->isOlympics()) {
            return $query;
        }

        foreach (\League\LeagueContext::TABLE_MAP as $iblTable => $olympicsTable) {
            // Only rewrite tables that opt in via a backtick-quoted reference.
            if (strpos($query, '`' . $iblTable . '`') === false) {
                continue;
            }

            $rewritten = preg_replace(
                '/(?<![A-Za-z0-9_])' . preg_quote($iblTable, '/') . '(?![A-Za-z0-9_])/',
                $olympicsTable,
                $query
            );

            // preg_replace returns null only on regex engine failure; keep the
            // current query rather than passing null downstream.
            $query = $rewritten ?? $query;
        }

        return $query;
    }

    /**
     * Execute a prepared statement and return the statement object
     *
     * Use this method when you need direct access to the statement for:
     * - Streaming large result sets row by row
     * - Accessing metadata (field info, etc.)
     * - Custom result handling
     *
     * IMPORTANT: Caller is responsible for closing the statement after use.
     *
     * @param string $query SQL query with ? placeholders
     * @param string $types Type specification string (default '' for no parameters)
     * @param mixed  ...$params Parameters to bind (must match $types length)
     * @return \mysqli_stmt Prepared and executed statement (caller must close)
     * @phpstan-return \mysqli_stmt
     *
     * @throws \RuntimeException Error 1001 if type/param count mismatch
     * @throws \RuntimeException Error 1002 if prepare fails
     * @throws \RuntimeException Error 1003 if execute fails
     *
     * @example
     * // Streaming large result set:
     * $stmt = $this->executeQuery("SELECT * FROM large_table WHERE status = ?", "s", "active");
     * $result = $stmt->get_result();
     * while ($row = $result->fetch_assoc()) {
     *     processRow($row);
     * }
     * $stmt->close();
     */
    protected function executeQuery(string $query, string $types = '', mixed ...$params): object
    {
        // Validate parameter count matches type string length
        $typeCount = strlen($types);
        $paramCount = count($params);

        if ($typeCount !== $paramCount) {
            $message = sprintf(
                'Type/parameter count mismatch: types=%d ("%s"), params=%d',
                $typeCount,
                $types,
                $paramCount
            );
            $this->logError($message, $query);
            throw new \RuntimeException($message, 1001);
        }
        $query = $this->rewriteTableNames($query);

        // Prepare statement
        $startTime = hrtime(true);

        /** @var \mysqli $db */
        $db = $this->db;
        $stmt = $db->prepare($query);
        if ($stmt === false) {
            /** @var string $dbError */
            $dbError = $db->error;
            $message = 'Failed to prepare query: ' . $dbError;
            $this->logError($message, $query);
            throw new \RuntimeException($message, 1002);
        }

        // Bind parameters if provided
        if ($typeCount > 0) {
            if ($stmt->bind_param($types, ...$params) === false) {
                /** @var string $stmtError */
                $stmtError = $stmt->error;
                $message = 'Failed to bind parameters: ' . $stmtError;
                $this->logError($message, $query);
                $stmt->close();
                throw new \RuntimeException($message, 1002);
            }
        }

        // Execute statement
        if ($stmt->execute() === false) {
            /** @var string $stmtError */
            $stmtError = $stmt->error;
            $message = 'Failed to execute query: ' . $stmtError;
            $this->logError($message, $query);
            $stmt->close();
            throw new \RuntimeException($message, 1003);
        }

        $thresholdMs = \Logging\LoggerFactory::getSlowQueryThresholdMs();
        if ($thresholdMs > 0) {
            $elapsedMs = (hrtime(true) - $startTime) / 1_000_000;
            if ($elapsedMs >= $thresholdMs) {
                \Logging\LoggerFactory::getChannel('perf')->warning('slow_query', [
                    'action' => 'slow_query',
                    'elapsed_ms' => round($elapsedMs, 1),
                    'query' => substr($query, 0, 500),
                    'repository' => static::class,
                ]);
            }
        }

        return $stmt;
    }

    /**
     * Execute query and fetch a single row
     *
     * Returns the first row matching the query, or null if no rows found.
     * Statement is automatically closed after fetching.
     *
     * @param string $query SQL query with ? placeholders
     * @param string $types Type specification string (default '' for no parameters)
     * @param mixed  ...$params Parameters to bind
     * @return array<string, mixed>|null Associative array of column values, or null if not found
     *
     * @throws \RuntimeException Error 1001/1002/1003 on failure (see executeQuery)
     *
     * @example
     * $player = $this->fetchOne("SELECT * FROM `ibl_plr` WHERE pid = ?", "i", 123);
     * // Returns ['pid' => 123, 'name' => 'John', ...] or null
     */
    protected function fetchOne(string $query, string $types = '', mixed ...$params): ?array
    {
        $stmt = $this->executeQuery($query, $types, ...$params);
        $result = $stmt->get_result();

        if ($result === false) {
            $message = 'Failed to get result: ' . $stmt->error;
            $this->logError($message, $query);
            $stmt->close();
            throw new \RuntimeException($message, 1003);
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        return is_array($row) && $row !== [] ? $row : null;
    }

    /**
     * Execute query and fetch all rows
     *
     * Returns all rows matching the query as an array of associative arrays.
     * Statement is automatically closed after fetching.
     *
     * WARNING: This loads all rows into memory. For large result sets (1000+ rows),
     * use executeQuery() directly and iterate with fetch_assoc() to stream results.
     *
     * @param string $query SQL query with ? placeholders
     * @param string $types Type specification string (default '' for no parameters)
     * @param mixed  ...$params Parameters to bind
     * @return array<int, array<string, mixed>> Array of rows (empty array if no results)
     *
     * @throws \RuntimeException Error 1001/1002/1003 on failure (see executeQuery)
     *
     * @example
     * $players = $this->fetchAll("SELECT * FROM `ibl_plr` WHERE teamid = ?", "i", 1);
     * // Returns [['pid' => 1, 'name' => 'John', ...], ['pid' => 2, 'name' => 'Jane', ...]]
     */
    protected function fetchAll(string $query, string $types = '', mixed ...$params): array
    {
        $stmt = $this->executeQuery($query, $types, ...$params);
        $result = $stmt->get_result();

        if ($result === false) {
            $message = 'Failed to get result: ' . $stmt->error;
            $this->logError($message, $query);
            $stmt->close();
            throw new \RuntimeException($message, 1003);
        }

        $rows = [];
        while (true) {
            $row = $result->fetch_assoc();
            if (!is_array($row) || $row === []) {
                break;
            }
            $rows[] = $row;
        }

        $stmt->close();
        return $rows;
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE statement
     *
     * Returns the number of affected rows. Statement is automatically closed.
     *
     * @param string $query SQL query with ? placeholders
     * @param string $types Type specification string (default '' for no parameters)
     * @param mixed  ...$params Parameters to bind
     * @return int Number of affected rows (0 if no rows affected)
     *
     * @throws \RuntimeException Error 1001/1002/1003 on failure (see executeQuery)
     *
     * @example
     * $affected = $this->execute("UPDATE `ibl_plr` SET teamid = ? WHERE pid = ?", "ii", 1, 123);
     * // Returns 1 if player was updated, 0 if pid not found
     */
    protected function execute(string $query, string $types = '', mixed ...$params): int
    {
        $stmt = $this->executeQuery($query, $types, ...$params);
        $affectedRows = $this->getAffectedRows($stmt);
        $stmt->close();

        return $affectedRows;
    }

    /**
     * Get affected rows from a prepared statement.
     *
     * Extracted as a protected method so test doubles can override it,
     * since mysqli_stmt::$affected_rows is a virtual/readonly property
     * that cannot be set on mock objects.
     */
    protected function getAffectedRows(object $stmt): int
    {
        /** @var \mysqli_stmt $stmt */
        return (int) $stmt->affected_rows;
    }

    /**
     * Fetch all real teams from `ibl_team_info` (excludes Free Agents, All-Star, etc.)
     *
     * @param string $orderBy One of 'team_name ASC', 'teamid ASC', or 'team_city ASC'
     * @return list<array<string, mixed>>
     */
    protected function fetchAllRealTeams(string $orderBy = 'team_name ASC'): array
    {
        $allowedOrderBy = [
            'team_name ASC' => 'team_name ASC',
            'teamid ASC' => 'teamid ASC',
            'team_city ASC' => 'team_city ASC',
        ];
        $safeOrderBy = $allowedOrderBy[$orderBy] ?? 'team_name ASC';

        /** @var list<array<string, mixed>> */
        return $this->fetchAll(
            "SELECT * FROM `ibl_team_info` WHERE teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . " ORDER BY $safeOrderBy"
        );
    }

    /**
     * Execute a query whose SQL contains `{IN}` as a placeholder for an IN-clause
     * list whose contents come from $ids. Returns [] when $ids is empty without
     * touching the database.
     *
     * @param string $query SQL with literal `{IN}` token to be replaced by `?,?,?,...`
     * @param string $type  Single mysqli type character ('i' or 's') applied to every $id
     * @param list<int|string> $ids
     * @param string $prefixTypes Type string for parameters that precede the IN-clause
     * @param mixed  ...$prefixParams Parameters bound before the IN-clause values
     * @return list<array<string, mixed>>
     */
    protected function fetchAllInList(
        string $query,
        string $type,
        array $ids,
        string $prefixTypes = '',
        mixed ...$prefixParams,
    ): array {
        if ($ids === []) {
            return [];
        }
        if (strlen($type) !== 1) {
            throw new \InvalidArgumentException('fetchAllInList: $type must be a single mysqli type character');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $expanded = str_replace('{IN}', $placeholders, $query);
        $types = $prefixTypes . str_repeat($type, count($ids));
        /** @var list<array<string, mixed>> */
        return $this->fetchAll($expanded, $types, ...$prefixParams, ...$ids);
    }

    /**
     * Canonical derived-table subquery deduplicating `ibl_box_scores_teams` rows
     * where the same (game_date, visitor_teamid, home_teamid) tuple has multiple
     * `game_of_that_day` values. Picks the smallest value (deterministic).
     *
     * Returns an SQL fragment usable as an inline derived table or CTE body.
     * Callers supply the outer JOIN/alias.
     */
    protected function gameOfThatDaySubquery(): string
    {
        // Backtick `ibl_box_scores_teams` so the executeQuery() rewrite resolves
        // it to the Olympics table on Olympics pages (stats subquery).
        return "(SELECT game_date, visitor_teamid, home_teamid, MIN(game_of_that_day) AS game_of_that_day
             FROM `ibl_box_scores_teams`
             GROUP BY game_date, visitor_teamid, home_teamid)";
    }

    /**
     * Get the ID generated by the last INSERT statement
     *
     * Call this immediately after an INSERT to get the auto-increment ID.
     *
     * @return int Last insert ID (0 if no insert was performed or table has no auto-increment)
     *
     * @example
     * $this->execute("INSERT INTO `ibl_plr` (name, pos) VALUES (?, ?)", "ss", "John", "PG");
     * $newPid = $this->getLastInsertId();
     */
    protected function getLastInsertId(): int
    {
        /** @var \mysqli $db */
        $db = $this->db;
        return (int) $db->insert_id;
    }

    /**
     * Execute a callable within a database transaction.
     *
     * If already inside a transaction (e.g., from DatabaseTestCase), uses a
     * SAVEPOINT instead of BEGIN to avoid implicitly committing the outer transaction.
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    protected function transactional(callable $fn): mixed
    {
        if ($this->isInTransaction()) {
            $savepoint = 'sp_' . bin2hex(random_bytes(4));
            $this->db->savepoint($savepoint);
            try {
                $result = $fn();
                $this->db->release_savepoint($savepoint);
                return $result;
            } catch (\Throwable $e) {
                $this->db->query("ROLLBACK TO SAVEPOINT " . $savepoint);
                throw $e;
            }
        }

        $this->db->begin_transaction();
        try {
            $result = $fn();
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Check if the connection is currently inside a transaction.
     */
    private function isInTransaction(): bool
    {
        try {
            $result = $this->db->query("SELECT @@in_transaction AS in_tx");
        } catch (\Throwable) {
            return false;
        }
        if (!$result instanceof \mysqli_result) {
            return false;
        }
        /** @var array{in_tx: int}|null $row */
        $row = $result->fetch_assoc();
        $result->free();
        return $row !== null && $row['in_tx'] === 1;
    }

    /**
     * Log database errors with structured context.
     *
     * Uses PSR-3 logger (via injected logger or LoggerFactory) with query
     * context and stack trace. Falls back to error_log() if no logger is
     * available (e.g. during very early bootstrap before LoggerFactory init).
     *
     * @param string $message Error message
     * @param string $query SQL query that caused the error
     */
    private function logError(string $message, string $query): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $traceInfo = [];
        foreach ($trace as $frame) {
            if (isset($frame['file'], $frame['line'])) {
                $traceInfo[] = basename($frame['file']) . ':' . $frame['line'];
            }
        }

        $context = [
            'query' => substr($query, 0, 500),
            'trace' => implode(' -> ', $traceInfo),
        ];

        $logger = $this->logger ?? \Logging\LoggerFactory::getChannel('db');
        $logger->error($message, $context);
    }
}
