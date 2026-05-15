<?php

declare(strict_types=1);

namespace Tests\Auth;

use Auth\AuthRepository;
use Auth\Contracts\AuthRepositoryInterface;
use Tests\WideUnit\WideUnitTestCase;

class AuthRepositoryTest extends WideUnitTestCase
{
    private AuthRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new AuthRepository($this->mockDb);
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(AuthRepositoryInterface::class, $this->repo);
    }

    public function testFindUserRolesByUsernameHit(): void
    {
        $this->mockDb->setMockData([['roles_mask' => 1]]);

        $result = $this->repo->findUserRolesByUsername('admin');

        self::assertNotNull($result);
        self::assertSame(1, $result['roles_mask']);
    }

    public function testFindUserRolesByUsernameMiss(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repo->findUserRolesByUsername('nobody');

        self::assertNull($result);
    }

    public function testFindUserInfoHit(): void
    {
        $this->mockDb->setMockData([['user_id' => 42, 'username' => 'testuser', 'user_email' => 'test@example.com']]);

        $result = $this->repo->findUserInfo('testuser');

        self::assertNotNull($result);
        self::assertSame(42, $result['user_id']);
        self::assertSame('testuser', $result['username']);
        self::assertSame('test@example.com', $result['user_email']);
    }

    public function testFindUserInfoMiss(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repo->findUserInfo('nobody');

        self::assertNull($result);
    }
}
