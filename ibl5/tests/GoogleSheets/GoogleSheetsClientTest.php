<?php

declare(strict_types=1);

namespace Tests\GoogleSheets;

use GoogleSheets\GoogleApiException;
use GoogleSheets\GoogleSheetMissingException;
use GoogleSheets\GoogleSheetsClient;
use PHPUnit\Framework\TestCase;
use Tests\GoogleSheets\Fakes\FakeGoogleHttpClient;

class GoogleSheetsClientTest extends TestCase
{
    private const TOKEN = 'ya29.access-token';
    private const SHEET_ID = 'sheet-abc';

    private FakeGoogleHttpClient $http;
    private GoogleSheetsClient $client;

    protected function setUp(): void
    {
        $this->http = new FakeGoogleHttpClient();
        $this->client = new GoogleSheetsClient($this->http);
    }

    /**
     * @return array<mixed>
     */
    private function body(int $index): array
    {
        $decoded = json_decode((string) $this->http->requests[$index]['body'], true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    public function testCreateSpreadsheetSendsSinglePlayersTabAndReturnsIdAndUrl(): void
    {
        $this->http->queue(200, ['spreadsheetId' => 'new-id', 'spreadsheetUrl' => 'https://docs.google.com/spreadsheets/d/new-id/edit']);

        $result = $this->client->createSpreadsheet(self::TOKEN, 'IBL Players Export');

        self::assertSame(['id' => 'new-id', 'url' => 'https://docs.google.com/spreadsheets/d/new-id/edit'], $result);
        self::assertCount(1, $this->http->requests);
        self::assertSame('POST', $this->http->requests[0]['method']);
        self::assertSame(GoogleSheetsClient::BASE_URL, $this->http->requests[0]['url']);
        $body = $this->body(0);
        self::assertSame('IBL Players Export', $body['properties']['title']);
        self::assertCount(1, $body['sheets']);
        self::assertSame('Players', $body['sheets'][0]['properties']['title']);
    }

    public function testEnsureTabAddsSheetWhenPlayersTabMissing(): void
    {
        $this->http->queue(200, ['sheets' => [['properties' => ['sheetId' => 0, 'title' => 'Sheet1', 'gridProperties' => ['rowCount' => 1000, 'columnCount' => 26]]]]]);
        $this->http->queue(200, ['replies' => [['addSheet' => ['properties' => ['sheetId' => 77, 'title' => 'Players']]]]]);

        $sheetId = $this->client->ensureTab(self::TOKEN, self::SHEET_ID, 'Players', 2000, 64);

        self::assertSame(77, $sheetId);
        self::assertCount(2, $this->http->requests);
        self::assertSame('GET', $this->http->requests[0]['method']);
        self::assertStringEndsWith(':batchUpdate', $this->http->requests[1]['url']);
        $add = $this->body(1)['requests'][0]['addSheet']['properties'];
        self::assertSame('Players', $add['title']);
        self::assertSame(['rowCount' => 2000, 'columnCount' => 64], $add['gridProperties']);
    }

    public function testEnsureTabNeverTouchesOtherTabs(): void
    {
        $this->http->queue(200, ['sheets' => [
            ['properties' => ['sheetId' => 5, 'title' => 'My Notes', 'gridProperties' => ['rowCount' => 10, 'columnCount' => 5]]],
            ['properties' => ['sheetId' => 9, 'title' => 'Players', 'gridProperties' => ['rowCount' => 100, 'columnCount' => 64]]],
        ]]);
        $this->http->queue(200, ['replies' => [[]]]);

        $sheetId = $this->client->ensureTab(self::TOKEN, self::SHEET_ID, 'Players', 2000, 64);

        self::assertSame(9, $sheetId);
        self::assertCount(2, $this->http->requests);
        $update = $this->body(1)['requests'][0]['updateSheetProperties'];
        self::assertSame(9, $update['properties']['sheetId']);
        self::assertSame(2000, $update['properties']['gridProperties']['rowCount']);
        foreach ($this->http->requests as $request) {
            self::assertStringNotContainsString('My Notes', (string) $request['body']);
            self::assertStringNotContainsString('"sheetId":5', (string) $request['body']);
        }
    }

    public function testOverwriteTabBatchWritesThenClearsTailHeaderAndRowsRaw(): void
    {
        $this->http->queue(200, []);
        $this->http->queue(200, []);

        $rows = [['Name', 'Pos'], ['Jordan', 'SG'], ['Pippen', 'SF']];
        $this->client->overwriteTab(self::TOKEN, self::SHEET_ID, 'Players', $rows);

        self::assertCount(2, $this->http->requests);
        self::assertStringEndsWith('/values:batchUpdate', $this->http->requests[0]['url']);
        self::assertStringEndsWith('/values/' . rawurlencode("'Players'!A4:ZZZ") . ':clear', $this->http->requests[1]['url']);
        self::assertSame('{}', $this->http->requests[1]['body']);
        $body = $this->body(0);
        self::assertSame('RAW', $body['valueInputOption']);
        self::assertCount(1, $body['data']);
        self::assertSame("'Players'!A1", $body['data'][0]['range']);
        self::assertSame($rows, $body['data'][0]['values']);
    }

    public function testOverwriteTabClearsThenBatchWritesHeaderAndRowsRaw(): void
    {
        $this->http->queue(200, []);
        $this->http->queue(200, []);

        $rows = [['Name'], ['Jordan']];
        $this->client->overwriteTab(self::TOKEN, self::SHEET_ID, 'Players', $rows);

        // The tab is cleared past the new data and the header plus rows are written RAW in one batch.
        self::assertCount(2, $this->http->requests);
        self::assertStringEndsWith(':clear', $this->http->requests[1]['url']);
        $body = $this->body(0);
        self::assertSame('RAW', $body['valueInputOption']);
        self::assertSame($rows, $body['data'][0]['values']);
    }

    public function testForbiddenPermissionDeniedIsNotTreatedAsMissingSheet(): void
    {
        $this->http->queue(403, ['error' => ['code' => 403, 'status' => 'PERMISSION_DENIED', 'message' => 'Google Sheets API has not been used in project.']]);

        try {
            $this->client->ensureTab(self::TOKEN, self::SHEET_ID, 'Players', 2000, 64);
            self::fail('Expected GoogleApiException');
        } catch (GoogleApiException $e) {
            self::assertNotInstanceOf(GoogleSheetMissingException::class, $e);
            self::assertSame(403, $e->status);
        }
    }

    public function testOverwriteTabChunksRowsAboveLimit(): void
    {
        $this->http->queue(200, []);
        $this->http->queue(200, []);
        $this->http->queue(200, []);

        $rows = [];
        for ($i = 0; $i < GoogleSheetsClient::WRITE_CHUNK_ROWS + 3; $i++) {
            $rows[] = ['r' . $i];
        }
        $this->client->overwriteTab(self::TOKEN, self::SHEET_ID, 'Players', $rows);

        // One batchUpdate request per chunk, then the tail clear.
        self::assertCount(3, $this->http->requests);
        self::assertStringEndsWith('/values:batchUpdate', $this->http->requests[0]['url']);
        self::assertStringEndsWith('/values:batchUpdate', $this->http->requests[1]['url']);
        self::assertStringEndsWith(':clear', $this->http->requests[2]['url']);

        $first = $this->body(0)['data'];
        self::assertCount(1, $first);
        self::assertSame("'Players'!A1", $first[0]['range']);
        self::assertCount(GoogleSheetsClient::WRITE_CHUNK_ROWS, $first[0]['values']);

        $second = $this->body(1)['data'];
        self::assertCount(1, $second);
        self::assertSame("'Players'!A" . (GoogleSheetsClient::WRITE_CHUNK_ROWS + 1), $second[0]['range']);
        self::assertCount(3, $second[0]['values']);
        self::assertSame(['r' . GoogleSheetsClient::WRITE_CHUNK_ROWS], $second[0]['values'][0]);
    }

    public function testMissingSpreadsheetRaisesSheetMissingException(): void
    {
        $this->http->queue(404, ['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.']]);

        $this->expectException(GoogleSheetMissingException::class);
        $this->client->ensureTab(self::TOKEN, self::SHEET_ID, 'Players', 2000, 64);
    }

    public function testNonMissingErrorRaisesPlainApiException(): void
    {
        $this->http->queue(500, ['error' => ['code' => 500, 'status' => 'INTERNAL']]);

        try {
            $this->client->overwriteTab(self::TOKEN, self::SHEET_ID, 'Players', [['a']]);
            self::fail('expected GoogleApiException');
        } catch (GoogleApiException $e) {
            self::assertNotInstanceOf(GoogleSheetMissingException::class, $e);
            self::assertSame(500, $e->status);
        }
    }

    public function testAccessTokenTravelsOnlyInAuthorizationHeader(): void
    {
        $this->http->queue(200, ['sheets' => [['properties' => ['sheetId' => 1, 'title' => 'Players', 'gridProperties' => ['rowCount' => 5000, 'columnCount' => 64]]]]]);
        $this->http->queue(200, []);
        $this->http->queue(200, []);

        $this->client->ensureTab(self::TOKEN, self::SHEET_ID, 'Players', 2000, 64);
        $this->client->overwriteTab(self::TOKEN, self::SHEET_ID, 'Players', [['h'], ['v']]);

        self::assertCount(3, $this->http->requests);
        foreach ($this->http->requests as $request) {
            self::assertContains('Authorization: Bearer ' . self::TOKEN, $request['headers']);
            self::assertStringNotContainsString(self::TOKEN, $request['url']);
            self::assertStringNotContainsString(self::TOKEN, (string) $request['body']);
        }
    }
}
