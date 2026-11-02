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
                $sheetId = $props['sheetId'] ?? null;
                if (!is_int($sheetId)) {
                    throw new GoogleApiException('Google API error (tab has no sheet id)', 200, 'no_sheet_id');
                }
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
            ? ($replies[0]['addSheet']['properties']['sheetId'] ?? null)
            : null;

        if (!is_int($newId)) {
            throw new GoogleApiException('Google API error (addSheet returned no sheet id)', 200, 'no_sheet_id');
        }

        return $newId;
    }

    /**
     * Write $rows starting at A1 as RAW values, in chunks, then clear the rows below them and the columns right of them.
     *
     * @param list<list<mixed>> $rows Header row first
     * @param int $gridRows Row count the tab is known to have (at least what ensureTab was asked for)
     * @param int $gridColumns Column count the tab is known to have
     * @throws GoogleSheetMissingException When the spreadsheet is gone
     */
    public function overwriteTab(#[\SensitiveParameter] string $accessToken, string $spreadsheetId, string $tab, array $rows, int $gridRows = self::DEFAULT_ROWS, int $gridColumns = self::DEFAULT_COLUMNS): void
    {
        $base = self::BASE_URL . '/' . rawurlencode($spreadsheetId);
        $range = "'" . str_replace("'", "''", $tab) . "'";

        if ($rows === []) {
            $this->clearRange($accessToken, $base, $range);

            return;
        }

        // Write first, then clear only the rows below the new data: a failed write
        // leaves the previous export intact instead of an empty tab. One request per
        // chunk keeps each body under Google's payload limit.
        foreach (array_chunk($rows, self::WRITE_CHUNK_ROWS) as $index => $chunk) {
            $this->send($accessToken, 'POST', $base . '/values:batchUpdate', [
                'valueInputOption' => 'RAW',
                'data' => [[
                    'range' => $range . '!A' . (($index * self::WRITE_CHUNK_ROWS) + 1),
                    'majorDimension' => 'ROWS',
                    'values' => $chunk,
                ]],
            ], true);
        }
        // One batchClear wipes the rows below the data and the columns right of the
        // header width, so a narrower export leaves no stale cells.
        // Both ranges end at the tab's real grid edge: Google rejects A1 ranges past the grid.
        $count = count($rows);
        $width = max(1, count($rows[0]));
        $ranges = [];
        if ($count + 1 <= $gridRows) {
            $ranges[] = $range . '!A' . ($count + 1) . ':' . self::columnLetter($gridColumns) . $gridRows;
        }
        if ($width + 1 <= $gridColumns) {
            $ranges[] = $range . '!' . self::columnLetter($width + 1) . '1:' . self::columnLetter($gridColumns) . min($count, $gridRows);
        }
        if ($ranges === []) {
            return;
        }
        $this->send($accessToken, 'POST', $base . '/values:batchClear', ['ranges' => $ranges], true);
    }

    private static function columnLetter(int $number): string
    {
        $letters = '';
        while ($number > 0) {
            $number--;
            $letters = chr(65 + ($number % 26)) . $letters;
            $number = intdiv($number, 26);
        }

        return $letters;
    }

    private function clearRange(#[\SensitiveParameter] string $accessToken, string $base, string $range): void
    {
        $this->send($accessToken, 'POST', $base . '/values/' . rawurlencode($range) . ':clear', (object) [], true);
    }

    /**
     * @param array<string, mixed>|object|null $payload
     * @return array<mixed>
     */
    private function send(#[\SensitiveParameter] string $accessToken, string $method, string $url, array|object|null $payload, bool $notFoundMeansMissing): array
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

        $decoded = GoogleJson::decode($response['body']);

        return is_array($decoded) ? $decoded : [];
    }
}
