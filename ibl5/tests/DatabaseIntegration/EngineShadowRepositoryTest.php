<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use EngineShadow\EngineShadowRepository;
use PHPUnit\Framework\Attributes\Test;

/**
 * Security: proves the shadow inserts are parameterized. A quote-bearing `pos`
 * value round-trips intact rather than corrupting the statement — a string-built
 * query would either error or store something different.
 */
final class EngineShadowRepositoryTest extends DatabaseTestCase
{
    #[Test]
    public function quoteBearingPosRoundTripsProvingParameterization(): void
    {
        $repo = new EngineShadowRepository($this->db);

        $injection = "a'\"b"; // 4 chars: fits VARCHAR(5); both quote types + would break a built query
        $repo->insertShadowPlayerBox(
            '2026-03-10', 3, 1, 1,
            42, 3, $injection,
            30, 5, 10, 4, 5, 2, 6, 2, 6, 5, 2, 2, 1, 3,
            12345, 2,
        );

        $stmt = $this->db->prepare(
            "SELECT pos FROM `ibl_box_scores_engine_shadow` WHERE pid = 42 LIMIT 1"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        /** @var array{pos: string}|null $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertIsArray($row);
        self::assertSame($injection, $row['pos'], 'pos was altered — insert is not parameterized');
    }

    #[Test]
    public function resolvesTeamIdsForKnownPidsOnly(): void
    {
        $this->insertTestPlayer(701, 'Known Player', ['teamid' => 5]);
        $repo = new EngineShadowRepository($this->db);

        $map = $repo->getTeamIdsForPids([701, 7029999]);

        self::assertSame(5, $map[701] ?? null);
        self::assertArrayNotHasKey(7029999, $map, 'unknown pid must not appear in the map');
    }
}
