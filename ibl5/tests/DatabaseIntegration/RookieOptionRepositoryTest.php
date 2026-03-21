<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use RookieOption\RookieOptionRepository;

/**
 * Tests RookieOptionRepository against real MariaDB — rookie option
 * contract year updates on ibl_plr (InnoDB, normal transaction rollback).
 */
class RookieOptionRepositoryTest extends DatabaseTestCase
{
    private RookieOptionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new RookieOptionRepository($this->db);
    }

    public function testUpdatePlayerRookieOptionSetsCy4ForFirstRoundPick(): void
    {
        $this->insertTestPlayer(200150001, 'Rookie Round1', ['cy4' => 0]);

        $result = $this->repo->updatePlayerRookieOption(200150001, 1, 2500);

        self::assertTrue($result);

        $stmt = $this->db->prepare('SELECT cy4 FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $pid = 200150001;
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(2500, $row['cy4']);
    }

    public function testUpdatePlayerRookieOptionSetsCy3ForSecondRoundPick(): void
    {
        $this->insertTestPlayer(200150002, 'Rookie Round2', ['cy3' => 0]);

        $result = $this->repo->updatePlayerRookieOption(200150002, 2, 1800);

        self::assertTrue($result);

        $stmt = $this->db->prepare('SELECT cy3 FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $pid = 200150002;
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(1800, $row['cy3']);
    }

    public function testUpdatePlayerRookieOptionReturnsFalseForUnknownPlayer(): void
    {
        $result = $this->repo->updatePlayerRookieOption(999999999, 1, 2500);

        self::assertFalse($result);
    }
}
