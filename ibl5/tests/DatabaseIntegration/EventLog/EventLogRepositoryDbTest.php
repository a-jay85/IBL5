<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\EventLog;

use EventLog\EventLogRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
final class EventLogRepositoryDbTest extends DatabaseTestCase
{
    private EventLogRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EventLogRepository($this->db);
    }

    public function testInsertWritesFullyPopulatedRow(): void
    {
        $id = $this->repo->insert(
            'SENTINEL_URI',
            'SENTINEL_ROUTE',
            'GET',
            'SENTINEL_USER',
            1,
            'SENTINEL_REFERER',
            'SENTINEL_UA',
            'SENTINEL_SESSION',
            'SENTINEL_CLASS'
        );

        self::assertGreaterThan(0, $id);

        $stmt = $this->db->prepare(
            'SELECT request_uri, route_name, http_method, username, team_id, referer, user_agent, session_id, traffic_class'
            . ' FROM `ibl_events` WHERE id = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('SENTINEL_URI', $row['request_uri']);
        self::assertSame('SENTINEL_ROUTE', $row['route_name']);
        self::assertSame('GET', $row['http_method']);
        self::assertSame('SENTINEL_USER', $row['username']);
        self::assertSame(1, $row['team_id']);
        self::assertSame('SENTINEL_REFERER', $row['referer']);
        self::assertSame('SENTINEL_UA', $row['user_agent']);
        self::assertSame('SENTINEL_SESSION', $row['session_id']);
        self::assertSame('SENTINEL_CLASS', $row['traffic_class']);
    }

    public function testInsertNullableColumnsStoreSqlNull(): void
    {
        $id = $this->repo->insert(
            '/ibl5/index.php',
            null,
            'GET',
            null,
            null,
            null,
            null,
            null,
            null
        );

        self::assertGreaterThan(0, $id);

        $stmt = $this->db->prepare(
            'SELECT route_name, username, team_id, referer, user_agent, session_id, traffic_class'
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
        self::assertNull($row['session_id']);
        self::assertNull($row['traffic_class']);
    }

    public function testInsertIsParameterizedAdversarialInputStoredVerbatim(): void
    {
        $adversarialUri = "/x'; DROP TABLE ibl_events;--";
        $adversarialRoute = "a')--";
        $adversarialUa = "Mozilla')/**/";

        $id = $this->repo->insert(
            $adversarialUri,
            $adversarialRoute,
            'GET',
            null,
            null,
            null,
            $adversarialUa,
            null,
            null
        );

        self::assertGreaterThan(0, $id);

        // Table must still exist (no injection succeeded).
        $result = $this->db->query('SELECT 1 FROM `ibl_events` LIMIT 1');
        self::assertNotFalse($result, 'ibl_events table must still exist after adversarial insert');

        // Stored values must match input byte-for-byte.
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

    public function testInsertReturnsUsableRowId(): void
    {
        $id = $this->repo->insert(
            '/ibl5/check-id.php',
            'check_id',
            'GET',
            'testgm',
            1,
            null,
            'TestUA/1.0',
            null,
            null
        );

        self::assertGreaterThan(0, $id);

        $stmt = $this->db->prepare('SELECT * FROM `ibl_events` WHERE id = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row, "Row with id=$id must exist");
        self::assertSame('/ibl5/check-id.php', $row['request_uri']);
        self::assertSame('check_id', $row['route_name']);
    }

    public function testUpdateOutcomeSetsStatusAndAction(): void
    {
        $id = $this->repo->insert(
            '/ibl5/trade.php',
            'trading',
            'POST',
            'testgm',
            1,
            null,
            'TestUA/1.0',
            null,
            null
        );
        self::assertGreaterThan(0, $id);

        $affected = $this->repo->updateOutcome($id, 404, 'trade_submitted');
        self::assertSame(1, $affected);

        $stmt = $this->db->prepare(
            'SELECT request_uri, route_name, http_method, username, team_id, user_agent, http_status, action'
            . ' FROM `ibl_events` WHERE id = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(404, $row['http_status']);
        self::assertSame('trade_submitted', $row['action']);
        // Original columns must be untouched.
        self::assertSame('/ibl5/trade.php', $row['request_uri']);
        self::assertSame('trading', $row['route_name']);
        self::assertSame('POST', $row['http_method']);
        self::assertSame('testgm', $row['username']);
        self::assertSame(1, $row['team_id']);
        self::assertSame('TestUA/1.0', $row['user_agent']);
    }

    public function testUpdateOutcomeAcceptsNullsAndLeavesColumnsNull(): void
    {
        $id = $this->repo->insert(
            '/ibl5/page.php',
            'page',
            'GET',
            null,
            null,
            null,
            null,
            null,
            null
        );
        self::assertGreaterThan(0, $id);

        // First set non-null values so the subsequent null update is a real change.
        $this->repo->updateOutcome($id, 200, 'some_action');

        // Now clear them — must store SQL NULL, not 0/''.
        $this->repo->updateOutcome($id, null, null);

        $stmt = $this->db->prepare('SELECT http_status, action FROM `ibl_events` WHERE id = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertNull($row['http_status']);
        self::assertNull($row['action']);
    }

    public function testUpdateOutcomeOnMissingRowAffectsNothing(): void
    {
        $affected = $this->repo->updateOutcome(PHP_INT_MAX, 200, 'x');
        self::assertSame(0, $affected);
    }
}
