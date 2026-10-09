<?php

declare(strict_types=1);

namespace Tests\League;

use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Pins the final ORDER BY tail of the trading, draft and free-agency queries
 * that ADR-0083 requires to end on a unique column.
 *
 * `assertStringEndsWith` fails if the tiebreaker is dropped, reordered or given
 * the wrong direction. It is the only revert-sensitive check for the sites that
 * carry an `@phpstan-ignore ibl.orderByMissingTiebreaker`, because the ignore
 * silences the rule whether or not the primary-key suffix is present.
 */
final class OrderByTiebreakerSqlPinTest extends TestCase
{
    private function assertLastQueryEndsWith(MockDatabase $db, string $expectedTail): void
    {
        $queries = $db->getExecutedQueries();
        self::assertNotSame([], $queries, 'repository method executed no query');
        $last = $queries[array_key_last($queries)];
        $sql = trim((string) preg_replace('/\s+/', ' ', $last));
        self::assertStringEndsWith($expectedTail, $sql);
    }

    public function testTradeFormPlayersOrderEndsWithPid(): void
    {
        $db = new MockDatabase();
        $db->setMockData([]);
        $repository = new \Trading\TradeFormRepository($db);

        $repository->getTeamPlayersForTrading(1);

        $this->assertLastQueryEndsWith($db, 'ORDER BY ordinal ASC, pid ASC');
    }

    public function testTradeFormDraftPicksOrderEndsWithPickid(): void
    {
        $db = new MockDatabase();
        $db->setMockData([]);
        $repository = new \Trading\TradeFormRepository($db);

        $repository->getTeamDraftPicksForTrading(1);

        $this->assertLastQueryEndsWith($db, 'ORDER BY dp.year, dp.round ASC, dp.pickid ASC');
    }

    public function testFreeAgencyAllPlayersExcludingTeamOrderEndsWithPid(): void
    {
        $db = new MockDatabase();
        $db->setMockData([]);
        $repository = new \FreeAgency\FreeAgencyRepository($db);

        $repository->getAllPlayersExcludingTeam(1);

        $this->assertLastQueryEndsWith($db, 'ORDER BY p.ordinal ASC, p.pid ASC');
    }

    public function testDraftClassPlayersOrderEndsWithId(): void
    {
        $db = new MockDatabase();
        $db->setMockData([]);
        $repository = new \Draft\DraftRepository($db, self::createStub(TeamIdentityRepositoryInterface::class));

        $repository->getAllDraftClassPlayers();

        $this->assertLastQueryEndsWith($db, 'ORDER BY dc.drafted, dc.name, dc.id ASC');
    }

    public function testDraftHistoryByYearOrderEndsWithPid(): void
    {
        $db = new MockDatabase();
        $db->setMockData([]);
        $repository = new \DraftHistory\DraftHistoryRepository($db);

        $repository->getDraftPicksByYear(2020);

        $this->assertLastQueryEndsWith($db, 'ORDER BY p.draftround ASC, p.draftpickno ASC, p.pid ASC');
    }

    public function testDraftHistoryByTeamOrderEndsWithPid(): void
    {
        $db = new MockDatabase();
        $db->setMockData([]);
        $repository = new \DraftHistory\DraftHistoryRepository($db);

        $repository->getDraftPicksByTeam('Metros');

        $this->assertLastQueryEndsWith($db, 'ORDER BY p.draftyear DESC, p.draftround ASC, p.draftpickno ASC, p.pid ASC');
    }

    public function testBuyoutLedgerCashConsiderationsOrderEndsWithId(): void
    {
        $db = new MockDatabase();
        $db->setMockData([]);
        $repository = new \Trading\BuyoutLedgerRepository($db);

        $repository->getTeamCashConsiderations(1);

        $this->assertLastQueryEndsWith($db, 'ORDER BY label ASC, id ASC');
    }

    public function testBuyoutLedgerBuyoutsOrderEndsWithId(): void
    {
        $db = new MockDatabase();
        $db->setMockData([]);
        $repository = new \Trading\BuyoutLedgerRepository($db);

        $repository->getTeamBuyouts(1);

        $this->assertLastQueryEndsWith($db, 'ORDER BY label ASC, id ASC');
    }

    public function testTradeOfferAllOffersOrderEndsWithId(): void
    {
        $db = new MockDatabase();
        $db->setMockData([]);
        $repository = new \Trading\TradeOfferRepository($db, 'localhost');

        $repository->getAllTradeOffers();

        $this->assertLastQueryEndsWith($db, 'ORDER BY tradeofferid ASC, id ASC');
    }

    public function testDraftPickLocatorForTeamOrderEndsWithPickid(): void
    {
        $db = new MockDatabase();
        $db->setMockData([]);
        $repository = new \DraftPickLocator\DraftPickLocatorRepository($db);

        $repository->getDraftPicksForTeam(1);

        $this->assertLastQueryEndsWith($db, 'ORDER BY year, round ASC, pickid ASC');
    }

    public function testDraftPickLocatorGroupedByTeamOrderEndsWithPickid(): void
    {
        $db = new MockDatabase();
        $db->setMockData([]);
        $repository = new \DraftPickLocator\DraftPickLocatorRepository($db);

        $repository->getAllDraftPicksGroupedByTeam();

        $this->assertLastQueryEndsWith($db, 'ORDER BY teampick_teamid, year, round ASC, pickid ASC');
    }

    public function testProjectedDraftOrderFinalOrderEndsWithDraftId(): void
    {
        $db = new MockDatabase();
        $db->setMockData([]);
        $repository = new \ProjectedDraftOrder\ProjectedDraftOrderRepository($db);

        $repository->getFinalDraftOrder(2099, 1);

        $this->assertLastQueryEndsWith($db, 'ORDER BY pick, draft_id ASC');
    }

    public function testFreeAgencyAdminOffersOrderEndsWithPrimaryKey(): void
    {
        $db = new MockDatabase();
        $db->setMockData([]);
        $repository = new \FreeAgency\Admin\FreeAgencyAdminRepository($db);

        $repository->getAllOffersWithBirdYears();

        $this->assertLastQueryEndsWith($db, 'ORDER BY ibl_fa_offers.name ASC, ibl_fa_offers.perceivedvalue DESC, ibl_fa_offers.primary_key ASC');
    }
}
