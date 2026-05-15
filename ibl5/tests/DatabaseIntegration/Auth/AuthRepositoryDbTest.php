<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Auth;

use Auth\AuthRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
class AuthRepositoryDbTest extends DatabaseTestCase
{
    private AuthRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new AuthRepository($this->db);
    }

    public function testFindUserRolesByUsernameReturnsRolesMask(): void
    {
        $result = $this->repo->findUserRolesByUsername('testadmin');

        self::assertNotNull($result);
        self::assertSame(1, $result['roles_mask']);
    }

    public function testFindUserRolesByUsernameReturnsZeroForNonAdmin(): void
    {
        $result = $this->repo->findUserRolesByUsername('testgm');

        self::assertNotNull($result);
        self::assertSame(0, $result['roles_mask']);
    }

    public function testFindUserRolesByUsernameReturnsNullForMissing(): void
    {
        $result = $this->repo->findUserRolesByUsername('nonexistent_user_xyz');

        self::assertNull($result);
    }

    public function testFindUserInfoReturnsExpectedShape(): void
    {
        $result = $this->repo->findUserInfo('testadmin');

        self::assertNotNull($result);
        self::assertSame(2, $result['user_id']);
        self::assertSame('testadmin', $result['username']);
        self::assertSame('admin@example.com', $result['user_email']);
    }

    public function testFindUserInfoReturnsNullForMissing(): void
    {
        $result = $this->repo->findUserInfo('nonexistent_user_xyz');

        self::assertNull($result);
    }
}
