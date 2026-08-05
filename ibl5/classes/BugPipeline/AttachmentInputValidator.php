<?php

declare(strict_types=1);

namespace BugPipeline;

/**
 * Validates raw attachment descriptors arriving at the enqueue trust boundary.
 *
 * Reject-and-skip: any entry that fails a per-field rule is dropped (and noted in
 * $rejectLog), never sanitized — surviving entries are returned. The pipeline treats a
 * dropped attachment exactly like a text-only report, so degrading is always safe.
 *
 * Every value here is attacker-controlled (Discord author plus relayed URL/path
 * metadata), so each field is narrowed from mixed and matched against a full-shape
 * allowlist — a prefix match would let `.../attachments/../../etc/passwd` through.
 *
 * @phpstan-import-type BugAttachmentInput from BugReportRepository
 */
final class AttachmentInputValidator
{
    /** Discord's own hard cap is 10 attachments per message; survivors past it are dropped. */
    public const MAX_ATTACHMENTS = 10;

    private const MAX_URL_LENGTH = 2048;
    private const MAX_FILENAME_LENGTH = 255;
    private const MAX_CONTENT_TYPE_LENGTH = 100;
    private const MAX_FILE_SIZE = 10485760; // 10 MiB — mirrors the byte cap the bot fetcher enforces.

    private const DEFAULT_HOSTS = ['cdn.discordapp.com', 'media.discordapp.net'];
    private const DEFAULT_CACHE_DIR = '/Users/ajaynicolas/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/bug-pipeline/attachments';

    /**
     * @param array<mixed> $raw       Decoded `attachments` array from the request body.
     * @param string|null  $rejectLog Out-param: '; '-joined reason for every dropped entry (null if none).
     * @return list<BugAttachmentInput>
     */
    public function validateAll(array $raw, ?string &$rejectLog = null): array
    {
        $rejectLog = null;

        // A JSON object (or otherwise non-list array) is malformed at this boundary — drop everything.
        if (!array_is_list($raw)) {
            $rejectLog = 'attachments payload is not a JSON array — all entries dropped';
            return [];
        }

        $valid = [];
        $rejects = [];
        foreach ($raw as $i => $entry) {
            // Cap on survivors, not positions: a prepended junk entry must not cost a real one.
            if (count($valid) >= self::MAX_ATTACHMENTS) {
                $rejects[] = "entry {$i}: dropped — exceeds MAX_ATTACHMENTS (" . self::MAX_ATTACHMENTS . ')';
                continue;
            }
            $reason = null;
            $clean = $this->validateEntry($entry, $reason);
            if ($clean === null) {
                $rejects[] = "entry {$i}: {$reason}";
                continue;
            }
            $valid[] = $clean;
        }

        if ($rejects !== []) {
            $rejectLog = implode('; ', $rejects);
        }
        return $valid;
    }

    /**
     * @return BugAttachmentInput|null  Null ⇒ dropped; $reason is set to why.
     */
    private function validateEntry(mixed $entry, ?string &$reason): ?array
    {
        if (!is_array($entry)) {
            $reason = 'not an object';
            return null;
        }

        $attachmentId = $entry['attachment_id'] ?? null;
        // Kept as a string; never (int)-cast — a 19-digit snowflake overflows past 2^53.
        if (!is_string($attachmentId) || preg_match('/^\d{1,20}$/', $attachmentId) !== 1) {
            $reason = 'attachment_id is not a 1-20 digit string';
            return null;
        }

        $originalUrl = $entry['original_url'] ?? null;
        if (!is_string($originalUrl) || !$this->isAllowedUrl($originalUrl)) {
            $reason = 'original_url is not an https URL on an allowed host';
            return null;
        }

        $localPath = $entry['local_path'] ?? null;
        if ($localPath !== null && (!is_string($localPath) || !$this->isAllowedCachePath($localPath))) {
            $reason = 'local_path is not null and not a well-formed cache path';
            return null;
        }

        $filename = $entry['filename'] ?? null;
        // strlen (bytes), not mb_strlen: the DB column is VARCHAR(255) (bytes), so a
        // multibyte name whose byte length exceeds 255 would truncate on insert.
        if (!is_string($filename) || $filename === '' || strlen($filename) > self::MAX_FILENAME_LENGTH
            || preg_match('/[\x00-\x1F\x7F]/', $filename) === 1
        ) {
            $reason = 'filename is empty, too long, or contains control characters';
            return null;
        }

        $contentType = $entry['content_type'] ?? null;
        if (!is_string($contentType) || $contentType === '' || strlen($contentType) > self::MAX_CONTENT_TYPE_LENGTH
            || preg_match('#^[\w.+-]+/[\w.+-]+$#', $contentType) !== 1
        ) {
            $reason = 'content_type is empty, too long, or not a MIME type';
            return null;
        }

        $fileSize = $entry['file_size'] ?? null;
        if ($fileSize !== null && (!is_int($fileSize) || $fileSize < 0 || $fileSize > self::MAX_FILE_SIZE)) {
            $reason = 'file_size is not null and not an int in [0, ' . self::MAX_FILE_SIZE . ']';
            return null;
        }

        return [
            'attachment_id' => $attachmentId,
            'original_url'  => $originalUrl,
            'local_path'    => $localPath,
            'filename'      => $filename,
            'content_type'  => $contentType,
            'file_size'     => $fileSize,
        ];
    }

    private function isAllowedUrl(string $url): bool
    {
        if (strlen($url) > self::MAX_URL_LENGTH) {
            return false;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host   = parse_url($url, PHP_URL_HOST);
        if ($scheme !== 'https' || !is_string($host)) {
            return false;
        }
        return in_array(strtolower($host), $this->allowedHosts(), true);
    }

    private function isAllowedCachePath(string $path): bool
    {
        // rtrim once: an env override with a trailing slash would otherwise yield `root//<name>`.
        $env = getenv('BUG_PIPELINE_ATTACHMENT_CACHE_DIR');
        $root = rtrim($env === false || $env === '' ? self::DEFAULT_CACHE_DIR : $env, '/');
        $pattern = '#^' . preg_quote($root, '#') . '/\d{1,20}-\d{1,20}\.(?:png|jpe?g|gif|webp)$#';
        return preg_match($pattern, $path) === 1;
    }

    /** @return list<string> */
    private function allowedHosts(): array
    {
        $override = getenv('BUG_PIPELINE_ATTACHMENT_HOSTS');
        if ($override === false || trim($override) === '') {
            return self::DEFAULT_HOSTS;
        }
        $hosts = [];
        foreach (explode(',', $override) as $h) {
            $h = strtolower(trim($h));
            if ($h !== '') {
                $hosts[] = $h;
            }
        }
        return $hosts === [] ? self::DEFAULT_HOSTS : $hosts;
    }
}
