<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Shared\SharedRepository;

/**
 * Tests SharedRepository against real MariaDB — draft pick ownership lookups
 * and contract extension resets.
 */
class SharedRepositoryTest extends DatabaseTestCase
{
    private SharedRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SharedRepository($this->db);
    }

    // ── getCurrentOwnerOfDraftPick ──────────────────────────────

    public function testGetCurrentOwnerOfDraftPickReturnsOwner(): void
    {
        $this->insertDraftPickRow(1, 2, 2099, 1, ['ownerofpick' => 'Metros', 'teampick' => 'Stars']);

        $result = $this->repo->getCurrentOwnerOfDraftPick(2099, 1, 2);

        self::assertSame('Metros', $result);
    }

    public function testGetCurrentOwnerOfDraftPickReturnsNullWhenNotFound(): void
    {
        $result = $this->repo->getCurrentOwnerOfDraftPick(9999, 9, 99);

        self::assertNull($result);
    }

    // ── resetSimContractExtensionAttempts ────────────────────────

    public function testResetSimContractExtensionAttemptsSetsToZero(): void
    {
        // Set Used_Extension_This_Chunk=1 for Metros within the transaction
        $stmt = $this->db->prepare('UPDATE ibl_team_info SET Used_Extension_This_Chunk = 1 WHERE team_name = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $tn);
        $tn = 'Metros';
        $stmt->execute();
        $stmt->close();

        $this->repo->resetSimContractExtensionAttempts();

        $stmt = $this->db->prepare('SELECT Used_Extension_This_Chunk FROM ibl_team_info WHERE team_name = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $tn);
        $tn = 'Metros';
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(0, $row['Used_Extension_This_Chunk']);
    }
}
