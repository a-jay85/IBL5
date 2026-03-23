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
     * Uses weak comparison per RFC 7232 §2.3.2 — strips W/ prefix before comparing
     * the opaque-tag. This handles mod_deflate converting strong ETags to weak ones.
     *
     * @return bool True if ETags match (caller should return 304), false otherwise
     */
    public function matches(string $etag): bool
    {
        $headerValue = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        $ifNoneMatch = is_string($headerValue) ? $headerValue : '';

        return $this->stripWeakPrefix($ifNoneMatch) === $this->stripWeakPrefix($etag);
    }

    private function stripWeakPrefix(string $etag): string
    {
        if (str_starts_with($etag, 'W/')) {
            return substr($etag, 2);
        }

        return $etag;
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
