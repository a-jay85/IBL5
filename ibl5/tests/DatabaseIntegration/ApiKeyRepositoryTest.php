<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Api\Repository\ApiKeyRepository;

/**
 * Tests ApiKeyRepository against real MariaDB —
 * API key lookup and last-used timestamp update.
 */
class ApiKeyRepositoryTest extends DatabaseTestCase
{
    private ApiKeyRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ApiKeyRepository($this->db);
    }

    // ── findByHash ──────────────────────────────────────────────

    public function testFindByHashReturnsActiveKey(): void
    {
        $keyHash = hash('sha256', 'test-api-key-batch7');

        $this->insertRow('ibl_api_keys', [
            'key_hash' => $keyHash,
            'key_prefix' => 'ibl_test',
            'owner_name' => 'DB Test Owner',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
            'is_active' => 1,
        ]);

        $result = $this->repo->findByHash($keyHash);

        self::assertNotNull($result);
        self::assertSame($keyHash, $result['key_hash']);
        self::assertSame('ibl_test', $result['key_prefix']);
        self::assertSame('DB Test Owner', $result['owner_name']);
        self::assertSame('public', $result['permission_level']);
        self::assertSame('standard', $result['rate_limit_tier']);
    }

    public function testFindByHashReturnsNullForInactiveKey(): void
    {
        $keyHash = hash('sha256', 'test-api-key-inactive-batch7');

        $this->insertRow('ibl_api_keys', [
            'key_hash' => $keyHash,
            'key_prefix' => 'ibl_inac',
            'owner_name' => 'DB Test Inactive',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
            'is_active' => 0,
        ]);

        $result = $this->repo->findByHash($keyHash);

        self::assertNull($result);
    }

    public function testFindByHashReturnsNullForNonexistentKey(): void
    {
        $result = $this->repo->findByHash('nonexistent_hash_value_0000000000000000000000000000000000');

        self::assertNull($result);
    }

    // ── touchLastUsed ───────────────────────────────────────────

    public function testTouchLastUsedUpdatesTimestamp(): void
    {
        $keyHash = hash('sha256', 'test-api-key-touch-batch7');

        $this->insertRow('ibl_api_keys', [
            'key_hash' => $keyHash,
            'key_prefix' => 'ibl_touc',
            'owner_name' => 'DB Test Touch',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
            'is_active' => 1,
        ]);

        // Verify last_used_at is initially NULL
        $stmt = $this->db->prepare('SELECT last_used_at FROM ibl_api_keys WHERE key_hash = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $keyHash);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        self::assertNull($row['last_used_at']);

        // Touch the key
        $this->repo->touchLastUsed($keyHash);

        // Verify last_used_at is now set
        $stmt = $this->db->prepare('SELECT last_used_at FROM ibl_api_keys WHERE key_hash = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $keyHash);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        self::assertNotNull($row['last_used_at']);
    }
}
