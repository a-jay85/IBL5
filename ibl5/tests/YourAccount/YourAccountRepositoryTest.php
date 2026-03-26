<?php

declare(strict_types=1);

namespace Tests\YourAccount;

use Tests\Integration\IntegrationTestCase;
use YourAccount\YourAccountRepository;

class YourAccountRepositoryTest extends IntegrationTestCase
{
    private YourAccountRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new YourAccountRepository($GLOBALS['mysqli_db']);
    }

    public function testUpdateLastLoginIpExecutesQuery(): void
    {
        $this->repository->updateLastLoginIp('testuser', '10.0.0.1');

        $this->assertQueryExecuted('nuke_users');
    }
}
