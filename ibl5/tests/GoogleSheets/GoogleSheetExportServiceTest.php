<?php

declare(strict_types=1);

namespace Tests\GoogleSheets;

use Api\Repository\ApiPlayerRepository;
use Api\Transformer\PlayerExportTransformer;
use GoogleSheets\Contracts\GoogleSheetConnectionRepositoryInterface;
use GoogleSheets\GoogleApiException;
use GoogleSheets\GoogleOAuthClient;
use GoogleSheets\GoogleOAuthConfig;
use GoogleSheets\GoogleSheetExportService;
use GoogleSheets\GoogleSheetsClient;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Security\SecretBox;
use Tests\GoogleSheets\Fakes\FakeGoogleHttpClient;
use Tests\GoogleSheets\Fakes\InMemoryConnectionRepository;

class GoogleSheetExportServiceTest extends TestCase
{
    private const USER_ID = 42;
    private const REFRESH_TOKEN = '1//refresh-token-secret';
    private const ACCESS_TOKEN = 'ya29.access-token-secret';
    private const SHEET_ID = 'sheet-123';
    private const SHEET_URL = 'https://docs.google.com/spreadsheets/d/sheet-123/edit';

    private FakeGoogleHttpClient $http;
    private InMemoryConnectionRepository $repo;
    private SecretBox $box;
    private TestHandler $logHandler;
    private GoogleSheetExportService $service;

    protected function setUp(): void
    {
        $this->http = new FakeGoogleHttpClient();
        $this->repo = new InMemoryConnectionRepository();
        $this->box = new SecretBox(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $this->logHandler = new TestHandler();
        $this->service = $this->buildService($this->box);
    }

    private function buildService(SecretBox $box): GoogleSheetExportService
    {
        $players = self::createStub(ApiPlayerRepository::class);
        $players->method('getAllPlayersForExport')->willReturn([['pid' => 1], ['pid' => 2]]);
        $transformer = self::createStub(PlayerExportTransformer::class);
        $transformer->method('getHeaders')->willReturn(['PID', 'Name']);
        $transformer->method('transform')->willReturnCallback(
            static fn (array $row): array => [(string) ($row['pid'] ?? ''), 'Player ' . (string) ($row['pid'] ?? '')]
        );

        return new GoogleSheetExportService(
            $this->repo,
            new GoogleOAuthClient(new GoogleOAuthConfig('cid', 'secret', 'http://x.localhost/cb'), $this->http),
            new GoogleSheetsClient($this->http),
            $box,
            $players,
            $transformer,
            new Logger('google-sheets', [$this->logHandler]),
        );
    }

    private function seedConnection(?string $refreshTokenEnc = null): void
    {
        $this->repo->upsert(self::USER_ID, $refreshTokenEnc ?? $this->box->encrypt(self::REFRESH_TOKEN), self::SHEET_ID, self::SHEET_URL);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        $row = $this->repo->findByUserId(self::USER_ID);
        self::assertNotNull($row);

        return $row;
    }

    private function queueAccessToken(): void
    {
        $this->http->queue(200, ['access_token' => self::ACCESS_TOKEN, 'expires_in' => 3599]);
    }

    private function queueSheetWriteOk(): void
    {
        $this->http->queue(200, ['sheets' => [['properties' => ['sheetId' => 0, 'title' => 'Players', 'gridProperties' => ['rowCount' => 5000, 'columnCount' => 64]]]]]);
        $this->http->queue(200, []);
        $this->http->queue(200, []);
    }

    private function queueSheetMissing(): void
    {
        $this->http->queue(404, ['error' => ['code' => 404, 'status' => 'NOT_FOUND']]);
    }

    private function allLogText(): string
    {
        $text = '';
        foreach ($this->logHandler->getRecords() as $record) {
            $text .= $record->message . ' ' . json_encode($record->context) . "\n";
        }

        return $text;
    }

    public function testRefreshConnectionOkPathMarksRefreshedOk(): void
    {
        $this->seedConnection();
        $this->queueAccessToken();
        $this->queueSheetWriteOk();

        $result = $this->service->refreshConnection($this->row());

        self::assertSame(GoogleSheetExportService::RESULT_OK, $result);
        $row = $this->row();
        self::assertSame('ok', $row['last_refresh_status']);
        self::assertNull($row['last_error']);
        self::assertSame(0, $row['refresh_pending']);
        self::assertSame('active', $row['status']);
        self::assertCount(4, $this->http->requests);
        $write = json_decode((string) $this->http->requests[3]['body'], true);
        self::assertIsArray($write);
        self::assertSame([['PID', 'Name'], ['1', 'Player 1'], ['2', 'Player 2']], $write['data'][0]['values']);
    }

    public function testRefreshConnectionInvalidGrantMarksBrokenAndDoesNotThrow(): void
    {
        $this->seedConnection();
        $this->http->queue(400, ['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.']);

        $result = $this->service->refreshConnection($this->row());

        self::assertSame(GoogleSheetExportService::RESULT_BROKEN, $result);
        self::assertSame('broken', $this->row()['status']);
        self::assertSame(GoogleSheetConnectionRepositoryInterface::REASON_INVALID_GRANT, $this->row()['broken_reason']);
        self::assertCount(1, $this->http->requests);
    }

    public function testRefreshConnectionSheetMissingMarksBrokenSheetMissing(): void
    {
        $this->seedConnection();
        $this->queueAccessToken();
        $this->queueSheetMissing();

        $result = $this->service->refreshConnection($this->row());

        self::assertSame(GoogleSheetExportService::RESULT_BROKEN, $result);
        self::assertSame('broken', $this->row()['status']);
        self::assertSame(GoogleSheetConnectionRepositoryInterface::REASON_SHEET_MISSING, $this->row()['broken_reason']);
    }

    public function testRefreshConnectionUndecryptableTokenMarksBrokenKeyUnavailable(): void
    {
        $other = new SecretBox(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $this->seedConnection($other->encrypt(self::REFRESH_TOKEN));

        $result = $this->service->refreshConnection($this->row());

        self::assertSame(GoogleSheetExportService::RESULT_BROKEN, $result);
        self::assertSame(GoogleSheetConnectionRepositoryInterface::REASON_KEY_UNAVAILABLE, $this->row()['broken_reason']);
        self::assertSame([], $this->http->requests);
    }

    public function testRefreshConnectionTransportErrorMarksErrorWithSanitizedReasonNoToken(): void
    {
        $this->seedConnection();
        $this->queueAccessToken();
        $this->http->queueTransportError('curl error 28 while sending ' . self::ACCESS_TOKEN);

        $result = $this->service->refreshConnection($this->row());

        self::assertSame(GoogleSheetExportService::RESULT_ERROR, $result);
        $row = $this->row();
        self::assertSame('active', $row['status']);
        self::assertSame('error', $row['last_refresh_status']);
        self::assertSame('0 transport_error', $row['last_error']);
        self::assertStringNotContainsString(self::ACCESS_TOKEN, $this->allLogText());
    }

    public function testRefreshConnectionApiErrorRecordsStatusAndReason(): void
    {
        $this->seedConnection();
        $this->queueAccessToken();
        $this->http->queue(429, ['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded']]);

        $result = $this->service->refreshConnection($this->row());

        self::assertSame(GoogleSheetExportService::RESULT_ERROR, $result);
        $error = $this->row()['last_error'];
        self::assertIsString($error);
        self::assertStringStartsWith('429 ', $error);
    }

    public function testRefreshConnectionReencryptsWhenPreviousKeyUsed(): void
    {
        $oldKey = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $newKey = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $oldEnc = (new SecretBox($oldKey))->encrypt(self::REFRESH_TOKEN);
        $rotated = new SecretBox($newKey, $oldKey);
        $this->service = $this->buildService($rotated);
        $this->seedConnection($oldEnc);
        $this->queueAccessToken();
        $this->queueSheetWriteOk();

        $result = $this->service->refreshConnection($this->row());

        self::assertSame(GoogleSheetExportService::RESULT_OK, $result);
        $stored = $this->row()['refresh_token_enc'];
        self::assertIsString($stored);
        self::assertNotSame($oldEnc, $stored);
        self::assertSame(self::REFRESH_TOKEN, (new SecretBox($newKey))->decrypt($stored));
    }

    public function testConnectReusesExistingSpreadsheetWhenTabEnsurable(): void
    {
        $this->seedConnection();
        $this->http->queue(200, ['access_token' => self::ACCESS_TOKEN, 'refresh_token' => '1//new-refresh']);
        $this->http->queue(200, ['sheets' => [['properties' => ['sheetId' => 0, 'title' => 'Players', 'gridProperties' => ['rowCount' => 5000, 'columnCount' => 64]]]]]);
        $this->queueAccessToken();
        $this->queueSheetWriteOk();

        $row = $this->service->connect(self::USER_ID, 'auth-code');

        self::assertSame(self::SHEET_ID, $row['spreadsheet_id']);
        self::assertSame('ok', $row['last_refresh_status']);
        self::assertSame('1//new-refresh', $this->box->decrypt($row['refresh_token_enc']));
        foreach ($this->http->requests as $request) {
            self::assertNotSame(GoogleSheetsClient::BASE_URL, $request['url']);
        }
    }

    public function testConnectCreatesNewSpreadsheetWhenOldOneMissing(): void
    {
        $this->seedConnection();
        $this->http->queue(200, ['access_token' => self::ACCESS_TOKEN, 'refresh_token' => '1//new-refresh']);
        $this->queueSheetMissing();
        $this->http->queue(200, ['spreadsheetId' => 'fresh-id', 'spreadsheetUrl' => 'https://docs.google.com/spreadsheets/d/fresh-id/edit']);
        $this->queueAccessToken();
        $this->queueSheetWriteOk();

        $row = $this->service->connect(self::USER_ID, 'auth-code');

        self::assertSame('fresh-id', $row['spreadsheet_id']);
        self::assertSame('https://docs.google.com/spreadsheets/d/fresh-id/edit', $row['spreadsheet_url']);
        self::assertSame('active', $row['status']);
        self::assertSame('ok', $row['last_refresh_status']);
    }

    public function testConnectStoresNothingWhenExchangeFails(): void
    {
        $this->http->queue(400, ['error' => 'invalid_request']);

        try {
            $this->service->connect(self::USER_ID, 'bad-code');
            self::fail('expected GoogleApiException');
        } catch (GoogleApiException $e) {
            self::assertSame(400, $e->status);
        }

        self::assertSame([], $this->repo->rows);
        self::assertCount(1, $this->http->requests);
    }

    public function testDisconnectRevokesThenDeletesEvenWhenRevokeFails(): void
    {
        $this->seedConnection();
        $this->http->queue(400, ['error' => 'invalid_token']);

        $this->service->disconnect(self::USER_ID);

        self::assertCount(1, $this->http->requests);
        self::assertSame(GoogleOAuthClient::REVOKE_URL, $this->http->requests[0]['url']);
        self::assertStringContainsString(rawurlencode(self::REFRESH_TOKEN), (string) $this->http->requests[0]['body']);
        self::assertNull($this->repo->findByUserId(self::USER_ID));
    }

    public function testDisconnectSkipsRevokeWhenTokenUndecryptableButStillDeletes(): void
    {
        $other = new SecretBox(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $this->seedConnection($other->encrypt(self::REFRESH_TOKEN));

        $this->service->disconnect(self::USER_ID);

        self::assertSame([], $this->http->requests);
        self::assertNull($this->repo->findByUserId(self::USER_ID));
    }

    public function testDisconnectSkipsRevokeWhenGrantAlreadyRevoked(): void
    {
        $this->seedConnection();
        $this->repo->markBroken(self::USER_ID, GoogleSheetConnectionRepositoryInterface::REASON_INVALID_GRANT);

        $this->service->disconnect(self::USER_ID);

        self::assertSame([], $this->http->requests);
        self::assertNull($this->repo->findByUserId(self::USER_ID));
    }

    public function testLogLinesNeverContainRefreshOrAccessTokens(): void
    {
        $this->http->queue(200, ['access_token' => self::ACCESS_TOKEN, 'refresh_token' => self::REFRESH_TOKEN]);
        $this->http->queue(200, ['spreadsheetId' => self::SHEET_ID, 'spreadsheetUrl' => self::SHEET_URL]);
        $this->queueAccessToken();
        $this->queueSheetWriteOk();
        $this->service->connect(self::USER_ID, 'auth-code');

        $this->http->queue(400, ['error' => 'invalid_grant']);
        $this->service->refreshConnection($this->row());
        $this->service->disconnect(self::USER_ID);

        $log = $this->allLogText();
        self::assertNotSame('', $log);
        self::assertStringNotContainsString(self::REFRESH_TOKEN, $log);
        self::assertStringNotContainsString(self::ACCESS_TOKEN, $log);
        self::assertStringNotContainsString('1//', $log);
        self::assertStringNotContainsString('ya29', $log);
    }

    public function testBuildRowsUsesTransformerHeadersAndEveryExportRow(): void
    {
        self::assertSame(
            [['PID', 'Name'], ['1', 'Player 1'], ['2', 'Player 2']],
            $this->service->buildRows()
        );
    }

    public function testConnectionSummaryOmitsRefreshTokenAndSpreadsheetId(): void
    {
        self::assertNull($this->service->connectionSummaryFor(self::USER_ID));

        $this->seedConnection();
        $summary = $this->service->connectionSummaryFor(self::USER_ID);

        self::assertNotNull($summary);
        self::assertSame(
            ['status', 'broken_reason', 'spreadsheet_url', 'last_refresh_at', 'last_refresh_status', 'last_error'],
            array_keys($summary)
        );
        self::assertSame(self::SHEET_URL, $summary['spreadsheet_url']);
    }

    public function testRefreshForUserReturnsMissingOrBrokenWithoutGoogleCall(): void
    {
        self::assertSame(GoogleSheetExportService::RESULT_MISSING, $this->service->refreshForUser(self::USER_ID));

        $this->seedConnection();
        $this->repo->markBroken(self::USER_ID, GoogleSheetConnectionRepositoryInterface::REASON_SHEET_MISSING);
        self::assertSame(GoogleSheetExportService::RESULT_BROKEN, $this->service->refreshForUser(self::USER_ID));
        self::assertSame([], $this->http->requests);
    }
}
