<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use Repositories\TeamIdentityRepository;

#[Group('database')]
final class TeamIdentityRepositoryUsernameTest extends DatabaseTestCase
{
    public function testGetTidFromUsernameMatchesGmUsername(): void
    {
        $this->db->query("UPDATE ibl_team_info SET gm_username = 'rivalry_probe_gm' WHERE teamid = 7");
        $repo = new TeamIdentityRepository($this->db);

        self::assertSame(7, $repo->getTidFromUsername('rivalry_probe_gm'));
        self::assertNull($repo->getTidFromUsername('no_such_gm_username'));
    }
}
