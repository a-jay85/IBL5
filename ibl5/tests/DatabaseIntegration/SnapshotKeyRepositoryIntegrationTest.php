<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use TradeSuggestions\Engine\SnapshotKeyRepository;

#[Group('database')]
final class SnapshotKeyRepositoryIntegrationTest extends DatabaseTestCase
{
    public function testRosterDigestRunsAgainstRealSchemaAndChangesOnTeamMove(): void
    {
        $this->insertTestPlayer(200000001, 'Digest One', ['teamid' => 1]);
        $this->insertTestPlayer(200000002, 'Digest Two', ['teamid' => 1]);
        $keys = new SnapshotKeyRepository($this->db);

        $before = $keys->rosterDigest();
        $this->db->query('UPDATE `ibl_plr` SET teamid = 2 WHERE pid = 200000002');
        $after = $keys->rosterDigest();

        self::assertNotSame('empty', $before);
        self::assertNotSame('empty', $after);
        self::assertNotSame($before, $after);
    }

    public function testTradeMarkerNonEmptyAfterTradeInfoRow(): void
    {
        $offerId = $this->insertTradeOfferRow();
        $this->insertTestPlayer(200000003, 'Marker Player', ['teamid' => 1]);
        $this->insertTradeInfoRow($offerId, 200000003, '1', 'One', 'Two');

        self::assertNotSame('', (new SnapshotKeyRepository($this->db))->tradeMarker());
    }
}
