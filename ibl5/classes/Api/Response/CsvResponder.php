<?php

declare(strict_types=1);

namespace Api\Response;

class CsvResponder
{
    /**
     * Send CSV response with appropriate headers.
     *
     * @param list<list<string>> $rows First row is the header row
     */
    public function send(array $rows, string $filename): void
    {
        http_response_code(200);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: X-API-Key, Content-Type');

        $handle = fopen('php://output', 'w');
        if ($handle === false) {
            return;
        }

        // UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");
        $this->renderRows($handle, $rows);
        fclose($handle);
    }

    /**
     * Write CSV rows to a stream. Extracted for testability.
     *
     * @param resource $handle Writable stream
     * @param list<list<string>> $rows
     */
    public function renderRows(mixed $handle, array $rows): void
    {
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }
    }
}
