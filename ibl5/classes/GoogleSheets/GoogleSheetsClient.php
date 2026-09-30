<?php

declare(strict_types=1);

namespace GoogleSheets;

use GoogleSheets\Contracts\GoogleHttpClientInterface;

/**
 * GoogleSheetsClient - the Sheets v4 calls the export needs.
 *
 * Only the tab named in a call is ever created, resized, cleared, or written;
 * no request names any other sheet. The access token travels only in the
 * Authorization header.
 */
class GoogleSheetsClient
{
    public const BASE_URL = 'https://sheets.googleapis.com/v4/spreadsheets';
    public const SHEET_TITLE = 'IBL Players Export';
    public const PLAYERS_TAB = 'Players';
    public const WRITE_CHUNK_ROWS = 5000;

    private const DEFAULT_ROWS = 2000;
    private const DEFAULT_COLUMNS = 64;

    public function __construct(private readonly GoogleHttpClientInterface $http)
    {
    }

    /**
     * Create a spreadsheet holding a single Players tab.
     *
     * @return array{id: string, url: string}
     */
    public function createSpreadsheet(#[\SensitiveParameter] string $accessToken, string $title): array
    {
        $response = $this->send($accessToken, 'POST', self::BASE_URL, [
            'properties' => ['title' => $title],
            'sheets' => [[
                'properties' => [
                    'title' => self::PLAYERS_TAB,
                    'gridProperties' => ['rowCount' => self::DEFAULT_ROWS, 'columnCount' => self::DEFAULT_COLUMNS],
                ],
            ]],
        ], false);

        $id = $response['spreadsheetId'] ?? null;
        $url = $response['spreadsheetUrl'] ?? null;
        if (!is_string($id) || $id === '' || !is_string($url)) {
            throw new GoogleApiException('Google API error (create returned no spreadsheet id)', 200, 'no_spreadsheet_id');
        }

        return ['id' => $id, 'url' => $url];
    }

    /**
     * Make sure $tab exists and is at least $rowCount x $columnCount. Returns its sheetId.
     *
     * @throws GoogleSheetMissingException When the spreadsheet is gone
     */
    public function ensureTab(#[\SensitiveParameter] string $accessToken, string $spreadsheetId, string $tab, int $rowCount, int $columnCount): int
    {
        $base = self::BASE_URL . '/' . rawurlencode($spreadsheetId);
        $response = $this->send(
            $accessToken,
            'GET',
            $base . '?fields=' . rawurlencode('sheets.properties(sheetId,title,gridProperties)'),
            null,
            true
        );

        $sheets = $response['sheets'] ?? [];
        if (is_array($sheets)) {
            foreach ($sheets as $sheet) {
                if (!is_array($sheet) || !is_array($sheet['properties'] ?? null)) {
                    continue;
                }
                $props = $sheet['properties'];
                if (($props['title'] ?? null) !== $tab) {
                    continue;
                }
                $sheetId = is_int($props['sheetId'] ?? null) ? $props['sheetId'] : 0;
                $grid = is_array($props['gridProperties'] ?? null) ? $props['gridProperties'] : [];
                $currentRows = is_int($grid['rowCount'] ?? null) ? $grid['rowCount'] : 0;
                $currentColumns = is_int($grid['columnCount'] ?? null) ? $grid['columnCount'] : 0;
                if ($currentRows < $rowCount || $currentColumns < $columnCount) {
                    $this->send($accessToken, 'POST', $base . ':batchUpdate', ['requests' => [[
                        'updateSheetProperties' => [
                            'properties' => [
                                'sheetId' => $sheetId,
                                'gridProperties' => [
                                    'rowCount' => max($rowCount, $currentRows),
                                    'columnCount' => max($columnCount, $currentColumns),
                                ],
                            ],
                            'fields' => 'gridProperties.rowCount,gridProperties.columnCount',
                        ],
                    ]]], true);
                }

                return $sheetId;
            }
        }

        $added = $this->send($accessToken, 'POST', $base . ':batchUpdate', ['requests' => [[
            'addSheet' => [
                'properties' => [
                    'title' => $tab,
                    'gridProperties' => ['rowCount' => $rowCount, 'columnCount' => $columnCount],
                ],
            ],
        ]]], true);

        $replies = $added['replies'] ?? null;
        $newId = is_array($replies) && is_array($replies[0] ?? null) && is_array($replies[0]['addSheet'] ?? null)
            && is_array($replies[0]['addSheet']['properties'] ?? null)
            ? ($replies[0]['addSheet']['properties']['sheetId'] ?? 0)
            : 0;

        return is_int($newId) ? $newId : 0;
    }

    /**
     * Clear $tab, then write $rows starting at A1 as RAW values, in chunks.
     *
     * @param list<list<mixed>> $rows Header row first
     * @throws GoogleSheetMissingException When the spreadsheet is gone
     */
    public function overwriteTab(#[\SensitiveParameter] string $accessToken, string $spreadsheetId, string $tab, array $rows): void
    {
        $base = self::BASE_URL . '/' . rawurlencode($spreadsheetId);
        $range = "'" . str_replace("'", "''", $tab) . "'";

        $this->send($accessToken, 'POST', $base . '/values/' . rawurlencode($range) . ':clear', [], true);

        $data = [];
        foreach (array_chunk($rows, self::WRITE_CHUNK_ROWS) as $index => $chunk) {
            $data[] = [
                'range' => $range . '!A' . (($index * self::WRITE_CHUNK_ROWS) + 1),
                'majorDimension' => 'ROWS',
                'values' => $chunk,
            ];
        }
        if ($data === []) {
            return;
        }

        $this->send($accessToken, 'POST', $base . '/values:batchUpdate', [
            'valueInputOption' => 'RAW',
            'data' => $data,
        ], true);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<mixed>
     */
    private function send(#[\SensitiveParameter] string $accessToken, string $method, string $url, ?array $payload, bool $notFoundMeansMissing): array
    {
        $body = $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR);
        $response = $this->http->request($method, $url, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ], $body);

        $status = $response['status'];
        if ($status < 200 || $status >= 300) {
            $e = GoogleApiException::fromResponse($status, $response['body']);
            if ($notFoundMeansMissing && $status === 404) {
                throw new GoogleSheetMissingException($e->getMessage(), $e->status, $e->reason);
            }
            throw $e;
        }

        $decoded = json_decode($response['body'], true);

        return is_array($decoded) ? $decoded : [];
    }
}
