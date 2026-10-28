<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

/**
 * Test-only mysqli wrapper that records every SQL statement the read path
 * sends, then delegates to the test's live connection.
 *
 * The parent constructor runs with no arguments, so no second connection is
 * opened, and statements prepared on the inner connection share the test
 * transaction, so fixtures stay visible. Every mysqli method the read path
 * uses (prepare, and query for `@@in_transaction`) is overridden here.
 *
 * With a $failCode, any statement naming `ibl_on_this_day_moments` is recorded
 * and then fails with a mysqli_sql_exception carrying that code, which mimics a
 * missing table (1146) or any other server error.
 */
final class RecordingMysqli extends \mysqli
{
    /** @var list<string> */
    public array $statements = [];

    public function __construct(private readonly \mysqli $inner, private readonly ?int $failCode = null)
    {
        // No arguments: like DatabaseTestCase's `new \mysqli()`, this opens no connection.
        parent::__construct();
    }

    public function prepare(string $query): \mysqli_stmt|false
    {
        $this->record($query);

        return $this->inner->prepare($query);
    }

    public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): \mysqli_result|bool
    {
        $this->record($query);

        return $this->inner->query($query, $resultMode);
    }

    private function record(string $query): void
    {
        $this->statements[] = $query;

        if ($this->failCode !== null && str_contains($query, 'ibl_on_this_day_moments')) {
            throw new \mysqli_sql_exception('forced', $this->failCode);
        }
    }
}
