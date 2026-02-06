<?php

declare(strict_types=1);

namespace RecordHolders;

use RecordHolders\Contracts\RecordHoldersServiceInterface;

/**
 * CachedRecordHoldersService - Caching decorator for RecordHoldersServiceInterface.
 *
 * Wraps an inner service and caches the getAllRecords() result in the `cache` database table.
 * On cache hit, returns data from the cache without calling the inner service (1 query).
 * On cache miss or expiration, delegates to the inner service and stores the result.
 *
 * @phpstan-import-type AllRecordsData from RecordHoldersServiceInterface
 */
class CachedRecordHoldersService implements RecordHoldersServiceInterface
{
    private const CACHE_KEY = 'record_holders';
    private const TTL_SECONDS = 86400; // 24 hours

    private RecordHoldersServiceInterface $inner;
    private object $db;

    public function __construct(RecordHoldersServiceInterface $inner, object $db)
    {
        $this->inner = $inner;
        $this->db = $db;
    }

    /**
     * @return AllRecordsData
     */
    public function getAllRecords(): array
    {
        $cached = $this->readCache();
        if ($cached !== null) {
            return $cached;
        }

        $records = $this->inner->getAllRecords();
        $this->writeCache($records);

        return $records;
    }

    /**
     * Remove the cached record holders data.
     */
    public function invalidateCache(): void
    {
        /** @var \mysqli $db */
        $db = $this->db;
        $stmt = $db->prepare("DELETE FROM `cache` WHERE `key` = ?");
        if ($stmt === false) {
            return;
        }
        $key = self::CACHE_KEY;
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Read cached data if it exists and hasn't expired.
     *
     * @return AllRecordsData|null
     */
    private function readCache(): ?array
    {
        /** @var \mysqli $db */
        $db = $this->db;
        $stmt = $db->prepare("SELECT `value`, `expiration` FROM `cache` WHERE `key` = ?");
        if ($stmt === false) {
            return null;
        }
        $key = self::CACHE_KEY;
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false) {
            $stmt->close();
            return null;
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        if (!is_array($row) || $row === []) {
            return null;
        }

        /** @var array{value: string, expiration: int} $row */
        if ($row['expiration'] < time()) {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($row['value'], true);
        if (!is_array($decoded)) {
            return null;
        }

        /** @var AllRecordsData $decoded */
        return $decoded;
    }

    /**
     * Write records data to the cache.
     *
     * @param AllRecordsData $records
     */
    private function writeCache(array $records): void
    {
        $encoded = json_encode($records);
        if ($encoded === false) {
            return;
        }

        /** @var \mysqli $db */
        $db = $this->db;
        $stmt = $db->prepare("REPLACE INTO `cache` (`key`, `value`, `expiration`) VALUES (?, ?, ?)");
        if ($stmt === false) {
            return;
        }
        $key = self::CACHE_KEY;
        $value = $encoded;
        $expiration = time() + self::TTL_SECONDS;
        $stmt->bind_param('ssi', $key, $value, $expiration);
        $stmt->execute();
        $stmt->close();
    }
}
