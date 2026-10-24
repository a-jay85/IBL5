<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use OnThisDay\OnThisDayRepository;
use PHPUnit\Framework\Attributes\Group;

/**
 * Proves the single-statement claim is a monotonic state machine against real
 * MariaDB: only a strictly newer sim advances the stored value.
 */
#[Group('database')]
class OnThisDaySimPostClaimTest extends DatabaseTestCase
{
    private OnThisDayRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->query(
            "DELETE FROM `ibl_settings` WHERE setting_key = '" . OnThisDayRepository::LAST_POSTED_SIM_KEY . "' AND league = 'ibl'"
        );
        $this->repository = new OnThisDayRepository($this->db);
    }

    public function testFirstClaimForSimSucceeds(): void
    {
        self::assertTrue($this->repository->claimSimPost(100));
    }

    public function testSecondClaimForSameSimFails(): void
    {
        self::assertTrue($this->repository->claimSimPost(100));
        self::assertFalse($this->repository->claimSimPost(100));
    }

    public function testClaimForNewerSimSucceedsAndOlderSimFails(): void
    {
        self::assertTrue($this->repository->claimSimPost(100));
        self::assertTrue($this->repository->claimSimPost(101));
        self::assertFalse($this->repository->claimSimPost(100));
        self::assertFalse($this->repository->claimSimPost(101));
    }
}
