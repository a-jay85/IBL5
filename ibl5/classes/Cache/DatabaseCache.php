<?php

declare(strict_types=1);

namespace Cache;

use Cache\Contracts\DatabaseCacheInterface;

/**
 * DatabaseCache - Generic key-value cache using the `cache` database table.
 *
 * Extracted from the pattern in CachedRecordHoldersService for reuse across modules.
 */
class DatabaseCache implements DatabaseCacheInterface
{
    private object $db;

    public function __construct(object $db)
    {
        $this->db = $db;
    }

    /**
     * @see DatabaseCacheInterface::get()
     *
     * @return list<array<string, mixed>>|null
     */
    public function get(string $key): ?array
    {
        /** @var \mysqli $db */
        $db = $this->db;
        $stmt = $db->prepare("SELECT `value`, `expiration` FROM `cache` WHERE `key` = ?");
        if ($stmt === false) {
            return null;
        }

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

        /** @var list<array<string, mixed>> $decoded */
        return $decoded;
    }

    /**
     * @see DatabaseCacheInterface::set()
     *
     * @param list<array<string, mixed>> $data
     */
    public function set(string $key, array $data, int $ttlSeconds): void
    {
        $encoded = json_encode($data);
        if ($encoded === false) {
            return;
        }

        /** @var \mysqli $db */
        $db = $this->db;
        $stmt = $db->prepare("REPLACE INTO `cache` (`key`, `value`, `expiration`) VALUES (?, ?, ?)");
        if ($stmt === false) {
            return;
        }

        $expiration = time() + $ttlSeconds;
        $stmt->bind_param('ssi', $key, $encoded, $expiration);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * @see DatabaseCacheInterface::delete()
     */
    public function delete(string $key): void
    {
        /** @var \mysqli $db */
        $db = $this->db;
        $stmt = $db->prepare("DELETE FROM `cache` WHERE `key` = ?");
        if ($stmt === false) {
            return;
        }

        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->close();
    }
}
