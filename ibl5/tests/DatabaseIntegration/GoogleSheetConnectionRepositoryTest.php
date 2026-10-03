<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use GoogleSheets\GoogleSheetConnectionRepository;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class GoogleSheetConnectionRepositoryTest extends DatabaseTestCase
{
    private GoogleSheetConnectionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new GoogleSheetConnectionRepository($this->db);
    }

    public function testFindByUserIdReturnsNullForUnknownUser(): void
    {
        $this->repo->upsert(900001, 'v1.cipher', 'sheet-a', 'https://docs.google.com/spreadsheets/d/sheet-a');

        self::assertNull($this->repo->findByUserId(999999));
    }

    public function testUpsertThenFindRoundTripsAllColumns(): void
    {
        $this->repo->upsert(900001, 'v1.opaque-cipher', 'sheet-a', 'https://docs.google.com/spreadsheets/d/sheet-a');

        $row = $this->repo->findByUserId(900001);

        self::assertNotNull($row);
        self::assertSame(900001, $row['user_id']);
        self::assertSame('v1.opaque-cipher', $row['refresh_token_enc']);
        self::assertSame('sheet-a', $row['spreadsheet_id']);
        self::assertSame('https://docs.google.com/spreadsheets/d/sheet-a', $row['spreadsheet_url']);
        self::assertSame('active', $row['status']);
        self::assertSame(0, $row['refresh_pending']);
        self::assertNull($row['broken_reason']);
        self::assertNull($row['last_refresh_at']);
    }

    public function testUpsertOnExistingUserReplacesTokenAndClearsBroken(): void
    {
        $this->repo->upsert(900001, 'v1.old', 'sheet-a', 'https://docs.google.com/spreadsheets/d/sheet-a');
        $this->repo->markBroken(900001, GoogleSheetConnectionRepository::REASON_INVALID_GRANT);

        $this->repo->upsert(900001, 'v1.new', 'sheet-b', 'https://docs.google.com/spreadsheets/d/sheet-b');

        $row = $this->repo->findByUserId(900001);
        self::assertNotNull($row);
        self::assertSame('active', $row['status']);
        self::assertNull($row['broken_reason']);
        self::assertSame('v1.new', $row['refresh_token_enc']);
        self::assertSame('sheet-b', $row['spreadsheet_id']);
        self::assertSame(1, $this->countRows(900001));
    }

    public function testMarkAllActivePendingSkipsBrokenRows(): void
    {
        $this->repo->upsert(900001, 'v1.a', 'sheet-a', 'https://docs.google.com/spreadsheets/d/sheet-a');
        $this->repo->upsert(900002, 'v1.b', 'sheet-b', 'https://docs.google.com/spreadsheets/d/sheet-b');
        $this->repo->markBroken(900002, GoogleSheetConnectionRepository::REASON_INVALID_GRANT);

        $affected = $this->repo->markAllActivePending();

        self::assertSame(1, $affected);
        $pending = $this->repo->findPending(10);
        self::assertCount(1, $pending);
        self::assertSame(900001, $pending[0]['user_id']);
    }

    public function testFindPendingOrdersNeverRefreshedFirstThenOldest(): void
    {
        $this->repo->upsert(900001, 'v1.a', 'sheet-a', 'https://docs.google.com/spreadsheets/d/sheet-a');
        $this->repo->upsert(900002, 'v1.b', 'sheet-b', 'https://docs.google.com/spreadsheets/d/sheet-b');
        $this->repo->upsert(900003, 'v1.c', 'sheet-c', 'https://docs.google.com/spreadsheets/d/sheet-c');
        // Insertion order is now / yesterday / never, the reverse of the expected order.
        $this->db->query("UPDATE ibl_google_sheet_connections SET last_refresh_at = NOW() WHERE user_id = 900001");
        $this->db->query("UPDATE ibl_google_sheet_connections SET last_refresh_at = NOW() - INTERVAL 1 DAY WHERE user_id = 900002");
        $this->repo->markAllActivePending();

        $pending = $this->repo->findPending(10);

        self::assertSame([900003, 900002, 900001], array_column($pending, 'user_id'));
        self::assertCount(2, $this->repo->findPending(2));
    }

    public function testMarkRefreshedTruncatesErrorTo255(): void
    {
        $this->repo->upsert(900001, 'v1.a', 'sheet-a', 'https://docs.google.com/spreadsheets/d/sheet-a');
        $this->repo->markAllActivePending();

        $this->repo->markRefreshed(900001, 'error', str_repeat('x', 400));

        $row = $this->repo->findByUserId(900001);
        self::assertNotNull($row);
        self::assertNotNull($row['last_error']);
        self::assertSame(255, strlen($row['last_error']));
        self::assertSame(0, $row['refresh_pending']);
        self::assertSame('error', $row['last_refresh_status']);
        self::assertNotNull($row['last_refresh_at']);
    }

    public function testMarkBrokenSetsReasonAndClearsPending(): void
    {
        $this->repo->upsert(900001, 'v1.a', 'sheet-a', 'https://docs.google.com/spreadsheets/d/sheet-a');
        $this->repo->markAllActivePending();

        $this->repo->markBroken(900001, GoogleSheetConnectionRepository::REASON_SHEET_MISSING);

        $row = $this->repo->findByUserId(900001);
        self::assertNotNull($row);
        self::assertSame('broken', $row['status']);
        self::assertSame('sheet_missing', $row['broken_reason']);
        self::assertSame(0, $row['refresh_pending']);
    }

    public function testDeleteByUserIdRemovesOnlyThatRow(): void
    {
        $this->repo->upsert(900001, 'v1.a', 'sheet-a', 'https://docs.google.com/spreadsheets/d/sheet-a');
        $this->repo->upsert(900002, 'v1.b', 'sheet-b', 'https://docs.google.com/spreadsheets/d/sheet-b');

        $this->repo->deleteByUserId(900001);

        self::assertNull($this->repo->findByUserId(900001));
        self::assertNotNull($this->repo->findByUserId(900002));
    }

    public function testAllQueriesAreParameterized(): void
    {
        $file = (new \ReflectionClass(GoogleSheetConnectionRepository::class))->getFileName();
        self::assertIsString($file);
        $source = file_get_contents($file);
        self::assertIsString($source);

        // No SQL string may be built by concatenating a PHP variable.
        self::assertSame(
            0,
            preg_match('/(SELECT|UPDATE|INSERT|DELETE)[^;]*?(\'|")\s*\.\s*\$/s', $source),
            'SQL string concatenates a variable'
        );
        self::assertSame(0, preg_match('/(SELECT|UPDATE|INSERT|DELETE)[^;"\']*\{?\$[a-zA-Z]/', $source), 'SQL string interpolates a variable');

        $methods = [
            'findByUserId', 'upsert', 'updateRefreshToken', 'markAllActivePending',
            'findPending', 'markRefreshed', 'markBroken', 'deleteByUserId',
        ];
        foreach ($methods as $method) {
            $reflection = new \ReflectionMethod(GoogleSheetConnectionRepository::class, $method);
            $start = $reflection->getStartLine();
            $end = $reflection->getEndLine();
            self::assertIsInt($start);
            self::assertIsInt($end);
            $body = implode("\n", array_slice(explode("\n", $source), $start - 1, $end - $start + 1));
            self::assertMatchesRegularExpression('/\$this->(fetchOne|fetchAll|execute)\(/', $body, $method);
        }
    }

    private function countRows(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS n FROM ibl_google_sheet_connections WHERE user_id = ?');
        self::assertInstanceOf(\mysqli_stmt::class, $stmt);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        self::assertIsArray($row);

        return (int) $row['n'];
    }
}
