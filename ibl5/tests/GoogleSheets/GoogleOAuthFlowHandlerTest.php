<?php

declare(strict_types=1);

namespace Tests\GoogleSheets;

use Api\Repository\ApiPlayerRepository;
use Api\Transformer\PlayerExportTransformer;
use GoogleSheets\GoogleOAuthClient;
use GoogleSheets\GoogleOAuthConfig;
use GoogleSheets\GoogleOAuthFlowHandler;
use GoogleSheets\GoogleOAuthState;
use GoogleSheets\GoogleSheetExportService;
use GoogleSheets\GoogleSheetsClient;
use PHPUnit\Framework\TestCase;
use Security\SecretBox;
use Tests\GoogleSheets\Fakes\FakeGoogleHttpClient;
use Tests\GoogleSheets\Fakes\InMemoryConnectionRepository;

class GoogleOAuthFlowHandlerTest extends TestCase
{
    private FakeGoogleHttpClient $http;
    private InMemoryConnectionRepository $repo;
    private GoogleOAuthFlowHandler $handler;

    protected function setUp(): void
    {
        $_SESSION = [];
        GoogleOAuthState::setTestClock(null);
        $this->http = new FakeGoogleHttpClient();
        $this->repo = new InMemoryConnectionRepository();

        $players = self::createStub(ApiPlayerRepository::class);
        $players->method('getAllPlayersForExport')->willReturn([]);
        $transformer = self::createStub(PlayerExportTransformer::class);
        $transformer->method('getHeaders')->willReturn(['PID']);

        $oauth = new GoogleOAuthClient(new GoogleOAuthConfig('cid', 'secret', 'http://x.localhost/cb'), $this->http);
        $export = new GoogleSheetExportService(
            $this->repo,
            $oauth,
            new GoogleSheetsClient($this->http),
            new SecretBox(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)),
            $players,
            $transformer,
        );
        $this->handler = new GoogleOAuthFlowHandler($oauth, $export);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function storedState(): string
    {
        $entry = $_SESSION[GoogleOAuthState::SESSION_KEY] ?? null;
        self::assertIsArray($entry);
        self::assertIsString($entry['value']);

        return $entry['value'];
    }

    public function testStartReturnsGoogleUrlCarryingIssuedState(): void
    {
        $url = $this->handler->start(5);

        self::assertStringStartsWith(GoogleOAuthClient::AUTH_URL . '?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame($this->storedState(), $query['state']);
        self::assertSame([], $this->http->requests);
    }

    public function testCallbackWithBadStateReturnsErrorAndSendsNoRequest(): void
    {
        $this->handler->start(5);

        $result = $this->handler->callback(5, 'bogus', 'code-1', null);

        self::assertSame(['type' => 'error', 'text' => GoogleOAuthFlowHandler::TEXT_BAD_STATE], $result);
        self::assertSame([], $this->http->requests);
        self::assertSame([], $this->repo->rows);
    }

    public function testCallbackWithMissingCodeReturnsBadStateError(): void
    {
        $this->handler->start(5);

        $result = $this->handler->callback(5, $this->storedState(), null, null);

        self::assertSame(['type' => 'error', 'text' => GoogleOAuthFlowHandler::TEXT_BAD_STATE], $result);
        self::assertSame([], $this->http->requests);
    }

    public function testCallbackWithUserDeniedErrorReturnsCancelledAndConsumesState(): void
    {
        $this->handler->start(5);
        $state = $this->storedState();

        $result = $this->handler->callback(5, $state, null, 'access_denied');

        self::assertSame(['type' => 'error', 'text' => GoogleOAuthFlowHandler::TEXT_CANCELLED], $result);
        self::assertFalse(GoogleOAuthState::consume($state, 5));
        self::assertSame([], $this->http->requests);
    }

    public function testCallbackSuccessConnectsAndReturnsSuccess(): void
    {
        $this->handler->start(5);
        $this->http->queue(200, ['access_token' => 'ya29.a', 'refresh_token' => '1//r']);
        $this->http->queue(200, ['spreadsheetId' => 'sid', 'spreadsheetUrl' => 'https://docs.google.com/spreadsheets/d/sid/edit']);
        $this->http->queue(200, ['access_token' => 'ya29.b']);
        $this->http->queue(200, ['sheets' => [['properties' => ['sheetId' => 0, 'title' => 'Players', 'gridProperties' => ['rowCount' => 5000, 'columnCount' => 64]]]]]);
        $this->http->queue(200, []);
        $this->http->queue(200, []);

        $result = $this->handler->callback(5, $this->storedState(), 'code-1', null);

        self::assertSame(['type' => 'success', 'text' => GoogleOAuthFlowHandler::TEXT_SUCCESS], $result);
        $row = $this->repo->findByUserId(5);
        self::assertNotNull($row);
        self::assertSame('sid', $row['spreadsheet_id']);
    }

    public function testCallbackExchangeFailureReturnsSanitizedErrorWithoutBody(): void
    {
        $this->handler->start(5);
        $this->http->queue(400, ['error' => 'invalid_grant', 'error_description' => 'secret-marker in body']);

        $result = $this->handler->callback(5, $this->storedState(), 'code-1', null);

        self::assertSame('error', $result['type']);
        self::assertSame('Could not connect Google Sheets (invalid_grant).', $result['text']);
        self::assertStringNotContainsString('secret-marker', $result['text']);
        self::assertSame([], $this->repo->rows);
    }

    public function testCallbackTransportFailureNamesNetwork(): void
    {
        $this->handler->start(5);
        $this->http->queueTransportError('curl 6: could not resolve host');

        $result = $this->handler->callback(5, $this->storedState(), 'code-1', null);

        self::assertSame(['type' => 'error', 'text' => 'Could not connect Google Sheets (network).'], $result);
    }

    public function testCallbackForOtherUsersStateIsRejected(): void
    {
        $this->handler->start(1);

        $result = $this->handler->callback(2, $this->storedState(), 'code-1', null);

        self::assertSame(['type' => 'error', 'text' => GoogleOAuthFlowHandler::TEXT_BAD_STATE], $result);
        self::assertSame([], $this->http->requests);
    }
}
