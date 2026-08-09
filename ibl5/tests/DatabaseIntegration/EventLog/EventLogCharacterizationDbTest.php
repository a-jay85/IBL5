<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\EventLog;

use EventLog\EventLogRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
final class EventLogCharacterizationDbTest extends DatabaseTestCase
{
    private EventLogRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EventLogRepository($this->db);
    }

    public function testFullyPopulatedRowRoundTripsEveryColumn(): void
    {
        $this->repo->insert(
            '/ibl5/modules.php?name=Team_Info',
            'Team_Info',
            'GET',
            'testgm',
            1,
            'https://example.com/ref',
            'Mozilla/5.0'
        );

        $stmt = $this->db->prepare(
            'SELECT request_uri, route_name, http_method, username, team_id, referer, user_agent'
            . ' FROM `ibl_events` WHERE username = ? ORDER BY id DESC LIMIT 1'
        );
        self::assertNotFalse($stmt);
        $username = 'testgm';
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('/ibl5/modules.php?name=Team_Info', $row['request_uri']);
        self::assertSame('Team_Info', $row['route_name']);
        self::assertSame('GET', $row['http_method']);
        self::assertSame('testgm', $row['username']);
        self::assertSame(1, $row['team_id']);
        self::assertSame('https://example.com/ref', $row['referer']);
        self::assertSame('Mozilla/5.0', $row['user_agent']);
    }

    public function testNullableColumnsRoundTripAsSqlNull(): void
    {
        $this->repo->insert(
            '/ibl5/index.php',
            null,
            'GET',
            null,
            null,
            null,
            null
        );

        $stmt = $this->db->prepare(
            'SELECT route_name, username, team_id, referer, user_agent'
            . ' FROM `ibl_events` ORDER BY id DESC LIMIT 1'
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertNull($row['route_name']);
        self::assertNull($row['username']);
        self::assertNull($row['team_id']);
        self::assertNull($row['referer']);
        self::assertNull($row['user_agent']);
    }

    public function testAdversarialInputIsStoredVerbatimNotExecuted(): void
    {
        $adversarialUri = "/x'; DROP TABLE ibl_events;--";
        $adversarialRoute = "a')--";
        $adversarialUa = "Mozilla')/**/";

        $this->repo->insert(
            $adversarialUri,
            $adversarialRoute,
            'GET',
            null,
            null,
            null,
            $adversarialUa
        );

        // Table must still exist (no injection succeeded).
        $result = $this->db->query('SELECT COUNT(*) AS cnt FROM `ibl_events`');
        self::assertNotFalse($result, 'ibl_events table must still exist after adversarial insert');
        $countRow = $result->fetch_assoc();
        self::assertNotNull($countRow);
        self::assertGreaterThan(0, (int) $countRow['cnt']);

        $stmt = $this->db->prepare(
            'SELECT request_uri, route_name, user_agent FROM `ibl_events` ORDER BY id DESC LIMIT 1'
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame($adversarialUri, $row['request_uri']);
        self::assertSame($adversarialRoute, $row['route_name']);
        self::assertSame($adversarialUa, $row['user_agent']);
    }
}
