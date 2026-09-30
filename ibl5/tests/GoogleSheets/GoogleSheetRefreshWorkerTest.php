<?php

declare(strict_types=1);

namespace Tests\GoogleSheets;

use Api\Repository\ApiPlayerRepository;
use Api\Transformer\PlayerExportTransformer;
use Clock\ClockInterface;
use GoogleSheets\GoogleOAuthClient;
use GoogleSheets\GoogleOAuthConfig;
use GoogleSheets\GoogleSheetExportService;
use GoogleSheets\GoogleSheetRefreshWorker;
use GoogleSheets\GoogleSheetsClient;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Security\SecretBox;
use Tests\Clock\FixedClock;
use Tests\GoogleSheets\Fakes\FakeGoogleHttpClient;
use Tests\GoogleSheets\Fakes\InMemoryConnectionRepository;

class GoogleSheetRefreshWorkerTest extends TestCase
{
    private const ACCESS_TOKEN = 'ya29.x';
    private const REFRESH_TOKEN = '1//rt-secret-value';

    private FakeGoogleHttpClient $http;
    private InMemoryConnectionRepository $repo;
    private SecretBox $box;

    /** @var list<string> */
    private array $output = [];

    protected function setUp(): void
    {
        $this->http = new FakeGoogleHttpClient();
        $this->repo = new InMemoryConnectionRepository();
        $this->box = new SecretBox(random_bytes(32));
        $this->output = [];
    }

    public function testDryRunListsPendingRowsAndMakesNoGoogleRequest(): void
    {
        $this->connect(7);
        $this->connect(8);
        $this->repo->markAllActivePending();
        $worker = $this->worker(new FixedClock(1000));

        $exit = $worker->run(100, 240, true, false, $this->collect());

        self::assertSame(0, $exit);
        self::assertSame([], $this->http->requests);
        self::assertCount(3, $this->output);
        self::assertStringContainsString('user_id=7', $this->output[0]);
        self::assertStringContainsString('spreadsheet_id=sheet-7', $this->output[0]);
        self::assertStringContainsString('last_refresh_at=never', $this->output[0]);
        self::assertStringContainsString('last_refresh_status=never', $this->output[0]);
        self::assertStringContainsString('user_id=8', $this->output[1]);
        self::assertSame('rows_to_write: 1', $this->output[2]);
        self::assertSame(1, $this->repo->rows[7]['refresh_pending']);
        self::assertSame(1, $this->repo->rows[8]['refresh_pending']);
    }

    public function testRunProcessesEveryPendingRowIndependentlyAndReturnsOneOnAnyError(): void
    {
        $this->connect(1);
        $this->connect(2);
        $this->connect(3);
        $this->connect(4);
        $this->repo->markAllActivePending();

        $this->queueOkRefresh();
        $this->http->queue(500, '{"error":{"message":"boom"}}');
        $this->http->queue(400, '{"error":"invalid_grant"}');
        $this->queueOkRefresh();

        $exit = $this->worker(new FixedClock(1000))->run(100, 240, false, false, $this->collect());

        self::assertSame(1, $exit);
        self::assertContains('user_id=1 status=ok', $this->output);
        self::assertContains('user_id=2 status=error', $this->output);
        self::assertContains('user_id=3 status=broken', $this->output);
        self::assertContains('user_id=4 status=ok', $this->output);
        self::assertSame('ok', $this->repo->rows[4]['last_refresh_status']);
        self::assertSame('broken', $this->repo->rows[3]['status']);
    }

    public function testRunStopsStartingRowsOnceBudgetElapsedLeavingRestPending(): void
    {
        $this->connect(1);
        $this->connect(2);
        $this->connect(3);
        $this->repo->markAllActivePending();
        $this->queueOkRefresh();

        // now() calls: start, row 1 check, row 2 check. The third call is past the budget.
        $clock = new class ([0, 0, 100]) implements ClockInterface {
            private int $calls = 0;

            /** @param list<int> $times */
            public function __construct(private readonly array $times)
            {
            }

            public function now(): int
            {
                $index = min($this->calls, count($this->times) - 1);
                $this->calls++;

                return $this->times[$index];
            }
        };

        $exit = $this->worker($clock)->run(100, 100, false, false, $this->collect());

        self::assertSame(0, $exit);
        self::assertContains('user_id=1 status=ok', $this->output);
        self::assertNotContains('user_id=2 status=ok', $this->output);
        self::assertSame(0, $this->repo->rows[1]['refresh_pending']);
        self::assertSame(1, $this->repo->rows[2]['refresh_pending']);
        self::assertSame(1, $this->repo->rows[3]['refresh_pending']);
        self::assertCount(4, $this->http->requests);
    }

    public function testRunHonorsLimit(): void
    {
        $this->connect(1);
        $this->connect(2);
        $this->connect(3);
        $this->repo->markAllActivePending();
        $this->queueOkRefresh();
        $this->queueOkRefresh();

        $exit = $this->worker(new FixedClock(1000))->run(2, 240, false, false, $this->collect());

        self::assertSame(0, $exit);
        self::assertCount(2, array_filter($this->output, static fn (string $line): bool => str_contains($line, 'status=ok')));
        self::assertSame(1, array_sum(array_column($this->repo->rows, 'refresh_pending')));
    }

    public function testRunReturnsZeroWhenAllOk(): void
    {
        $this->connect(1);
        $this->connect(2);
        $this->repo->markAllActivePending();
        $this->queueOkRefresh();
        $this->queueOkRefresh();

        $exit = $this->worker(new FixedClock(1000))->run(100, 240, false, false, $this->collect());

        self::assertSame(0, $exit);
    }

    public function testRunReturnsZeroWhenNothingPending(): void
    {
        $this->connect(1);

        $exit = $this->worker(new FixedClock(1000))->run(100, 240, false, false, $this->collect());

        self::assertSame(0, $exit);
        self::assertSame([], $this->http->requests);
    }

    public function testAllFlagMarksActiveRowsPendingBeforeDraining(): void
    {
        $this->connect(1);
        $this->connect(2);
        $this->connect(3);
        $this->repo->markBroken(3, 'invalid_grant');
        self::assertSame(0, $this->repo->rows[1]['refresh_pending']);
        $this->queueOkRefresh();
        $this->queueOkRefresh();

        $exit = $this->worker(new FixedClock(1000))->run(100, 240, false, true, $this->collect());

        self::assertSame(0, $exit);
        self::assertContains('user_id=1 status=ok', $this->output);
        self::assertContains('user_id=2 status=ok', $this->output);
        self::assertCount(2, array_filter($this->output, static fn (string $line): bool => str_contains($line, 'status=')
            && str_starts_with($line, 'user_id=')));
    }

    public function testRunLogsNoTokens(): void
    {
        $this->connect(1);
        $this->connect(2);
        $this->connect(3);
        $this->repo->markAllActivePending();
        $this->queueOkRefresh();
        $this->http->queue(500, '{"error":{"message":"boom"}}');
        $this->http->queue(400, '{"error":"invalid_grant"}');

        $handler = new TestHandler();
        $logger = new Logger('google-sheets', [$handler]);
        $worker = $this->worker(new FixedClock(1000), $logger);

        $worker->run(100, 240, false, false, $this->collect());

        self::assertNotEmpty($handler->getRecords());
        $haystack = $this->output;
        foreach ($handler->getRecords() as $record) {
            $haystack[] = $record->message;
            $haystack[] = (string) json_encode($record->context);
        }
        $joined = implode("\n", $haystack);
        self::assertStringNotContainsString(self::ACCESS_TOKEN, $joined);
        self::assertStringNotContainsString(self::REFRESH_TOKEN, $joined);
        foreach ($this->repo->rows as $row) {
            self::assertStringNotContainsString($row['refresh_token_enc'], $joined);
        }
    }

    private function connect(int $userId): void
    {
        $this->repo->upsert(
            $userId,
            $this->box->encrypt(self::REFRESH_TOKEN),
            'sheet-' . $userId,
            'https://docs.google.com/spreadsheets/d/sheet-' . $userId
        );
    }

    /**
     * Token exchange, tab lookup, clear, and write: four Google responses.
     */
    private function queueOkRefresh(): void
    {
        $this->http->queue(200, ['access_token' => self::ACCESS_TOKEN]);
        $this->http->queue(200, ['sheets' => [[
            'properties' => [
                'sheetId' => 1,
                'title' => GoogleSheetsClient::PLAYERS_TAB,
                'gridProperties' => ['rowCount' => 5000, 'columnCount' => 100],
            ],
        ]]]);
        $this->http->queue(200, []);
        $this->http->queue(200, []);
    }

    private function worker(ClockInterface $clock, ?Logger $logger = null): GoogleSheetRefreshWorker
    {
        $players = self::createStub(ApiPlayerRepository::class);
        $players->method('getAllPlayersForExport')->willReturn([]);
        $transformer = self::createStub(PlayerExportTransformer::class);
        $transformer->method('getHeaders')->willReturn(['Name', 'Team']);

        $export = new GoogleSheetExportService(
            $this->repo,
            new GoogleOAuthClient(
                new GoogleOAuthConfig('cid.apps.googleusercontent.com', 'GOCSPX-secret', 'http://x.localhost/cb'),
                $this->http
            ),
            new GoogleSheetsClient($this->http),
            $this->box,
            $players,
            $transformer,
            $logger
        );

        return new GoogleSheetRefreshWorker($this->repo, $export, $clock, $logger);
    }

    /**
     * @return callable(string): void
     */
    private function collect(): callable
    {
        return function (string $line): void {
            $this->output[] = $line;
        };
    }
}
