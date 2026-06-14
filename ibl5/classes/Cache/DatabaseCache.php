<?php

declare(strict_types=1);

namespace Cache;

use Cache\Contracts\DatabaseCacheInterface;

/**
 * DatabaseCache - Generic key-value cache using the `cache` database table.
 *
 * Extracted from the pattern in CachedRecordHoldersService for reuse across modules.
 */
class DatabaseCache extends \BaseMysqliRepository implements DatabaseCacheInterface
{
    /** PSR-3 logger for cache-layer failure logging; defaults to the 'db' channel. */
    private \Psr\Log\LoggerInterface $channelLogger;

    public function __construct(\mysqli $db, ?\Psr\Log\LoggerInterface $logger = null)
    {
        parent::__construct($db);
        $this->channelLogger = $logger ?? \Logging\LoggerFactory::getChannel('db');
    }

    /**
     * @see DatabaseCacheInterface::get()
     *
     * @return array<mixed>|null
     */
    public function get(string $key): ?array
    {
        try {
            $row = $this->fetchOne(
                "SELECT `value`, `expiration` FROM `cache` WHERE `cache_key` = ?",
                's',
                $key
            );
        } catch (\RuntimeException $e) {
            $this->channelLogger->error('cache_get_failed', ['cache_key' => $key, 'error' => $e->getMessage()]);
            return null;
        }

        if ($row === null) {
            return null; // cache miss — NOT an error, do not log
        }

        /** @var array{value: string, expiration: int} $row */
        if ($row['expiration'] < time()) {
            return null; // expired — not an error
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($row['value'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->channelLogger->warning('cache_corrupt_json', ['cache_key' => $key, 'error' => $e->getMessage()]);
            return null;
        }
        if (!is_array($decoded)) {
            $this->channelLogger->warning('cache_corrupt_json', ['cache_key' => $key, 'error' => 'decoded value is not an array']);
            return null;
        }

        /** @var array<mixed> $decoded */
        return $decoded;
    }

    /**
     * @see DatabaseCacheInterface::set()
     *
     * @param array<mixed> $data
     */
    public function set(string $key, array $data, int $ttlSeconds): void
    {
        $encoded = json_encode($data);
        if ($encoded === false) {
            $this->channelLogger->warning('cache_set_encode_failed', ['cache_key' => $key, 'error' => json_last_error_msg()]);
            return;
        }

        $expiration = time() + $ttlSeconds;
        try {
            $this->execute(
                "REPLACE INTO `cache` (`cache_key`, `value`, `expiration`) VALUES (?, ?, ?)",
                'ssi',
                $key,
                $encoded,
                $expiration
            );
        } catch (\RuntimeException $e) {
            $this->channelLogger->error('cache_set_failed', ['cache_key' => $key, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @see DatabaseCacheInterface::delete()
     */
    public function delete(string $key): void
    {
        try {
            $this->execute("DELETE FROM `cache` WHERE `cache_key` = ?", 's', $key);
        } catch (\RuntimeException $e) {
            $this->channelLogger->error('cache_delete_failed', ['cache_key' => $key, 'error' => $e->getMessage()]);
        }
    }
}
