<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Fixtures;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
class AdminFixtureTest extends DatabaseTestCase
{
    public function testAdminUserSeeded(): void
    {
        $row = $this->db->query(
            "SELECT id, username, roles_mask, status FROM auth_users WHERE username = '" . SeedUsers::ADMIN_USERNAME . "'"
        )->fetch_assoc();

        self::assertNotNull($row, 'testadmin user must exist in CI seed');
        self::assertSame(SeedUsers::ADMIN_ID, (int) $row['id']);
        self::assertSame(1, (int) $row['roles_mask'], 'testadmin must have ADMIN role bit set');
        self::assertSame(0, (int) $row['status'], 'testadmin must have status = NORMAL (0)');
    }

    public function testAdminPasswordVerifies(): void
    {
        $row = $this->db->query(
            "SELECT password FROM auth_users WHERE username = '" . SeedUsers::ADMIN_USERNAME . "'"
        )->fetch_assoc();

        self::assertNotNull($row);
        self::assertTrue(
            password_verify(SeedUsers::ADMIN_PASSWORD, $row['password']),
            'Seeded admin password hash must verify against the documented test password'
        );
    }

    public function testGmUserStillSeeded(): void
    {
        $row = $this->db->query(
            "SELECT id, roles_mask FROM auth_users WHERE username = '" . SeedUsers::GM_USERNAME . "'"
        )->fetch_assoc();

        self::assertNotNull($row);
        self::assertSame(SeedUsers::GM_ID, (int) $row['id']);
        self::assertSame(0, (int) $row['roles_mask']);
    }

    public function testGmPasswordVerifies(): void
    {
        $row = $this->db->query(
            "SELECT password FROM auth_users WHERE username = '" . SeedUsers::GM_USERNAME . "'"
        )->fetch_assoc();

        self::assertNotNull($row);
        self::assertTrue(
            password_verify(SeedUsers::GM_PASSWORD, $row['password']),
            'Seeded GM password hash must verify against the documented test password'
        );
    }
}
