<?php

declare(strict_types=1);

namespace Api\Cache;

class ETagHandler
{
    /**
     * Generate an ETag from an updated_at timestamp string.
     *
     * @param string $updatedAt  Timestamp string (e.g., "2026-01-15 12:00:00")
     */
    public function generate(string $updatedAt): string
    {
        return '"' . md5($updatedAt) . '"';
    }

    /**
     * Generate an ETag from a collection of rows by hashing all their updated_at values.
     *
     * @param array<int, array<string, mixed>> $rows  Rows with 'updated_at' field
     */
    public function generateFromCollection(array $rows): string
    {
        $timestamps = '';
        foreach ($rows as $row) {
            if (isset($row['updated_at']) && is_string($row['updated_at'])) {
                $timestamps .= $row['updated_at'];
            }
        }

        return '"' . md5($timestamps) . '"';
    }

    /**
     * Check if the client sent an If-None-Match header matching the given ETag.
     * If matched, this sends a 304 response and returns true.
     *
     * @return bool True if the response was 304 (caller should stop), false otherwise
     */
    public function matches(string $etag): bool
    {
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        return $ifNoneMatch === $etag;
    }

    /**
     * Build cache headers for the response.
     *
     * @return array<string, string> Headers to add to the response
     */
    public function getHeaders(string $etag, int $maxAgeSecs = 60): array
    {
        return [
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=' . $maxAgeSecs,
        ];
    }
}
