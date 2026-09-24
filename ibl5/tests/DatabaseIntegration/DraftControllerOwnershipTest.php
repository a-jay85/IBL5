<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use Draft\DraftController;
use Draft\DraftProcessor;
use Draft\DraftRepository;
use Draft\DraftValidator;
use Draft\DraftView;
use Draft\Contracts\DraftServiceInterface;
use Repositories\TeamIdentityRepository;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Season\Season;
use Security\CsrfGuard;

/**
 * submitSelection() check (4) against real ibl_draft / ibl_draft_picks rows:
 * a GM can land a pick only for a slot their team currently owns.
 */
#[Group('database')]
class DraftControllerOwnershipTest extends DatabaseTestCase
{
    private const YEAR = 2098;

    protected function setUp(): void
    {
        parent::setUp();
        // processDraftSelection() opens its own transaction, which would implicitly
        // commit the outer one anyway; commit explicitly and clean up in tearDown().
        $this->db->commit();
        // Pre-clean any stale ibl_draft rows for the round/pick slots used by these tests.
        // getOriginTeamIdForPick() has no year filter by design, so leftover rows from a
        // prior interrupted run would shadow our freshly-inserted test rows.
        $this->db->query('DELETE FROM `ibl_draft` WHERE `round` = 1 AND `pick` IN (1, 2, 3, 4, 5)');
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM ibl_plr WHERE name LIKE 'OwnershipTest %'");
        $this->db->query("DELETE FROM ibl_draft_class WHERE name LIKE 'OwnershipTest %'");
        $this->db->query('DELETE FROM ibl_draft WHERE year = ' . self::YEAR);
        $this->db->query('DELETE FROM ibl_draft_picks WHERE year IN (' . (self::YEAR - 1) . ', ' . self::YEAR . ')');
        $_SESSION = [];
        $_POST = [];
        CsrfGuard::setTestClock(null);
        parent::tearDown();
    }

    private function metrosController(): DraftController
    {
        $session = self::createStub(TeamIdentityRepositoryInterface::class);
        $session->method('getTeamnameFromUsername')->willReturn('Metros');

        $season = self::createStub(Season::class);
        $season->beginningYear = self::YEAR - 1;
        $season->endingYear = self::YEAR;
        $season->phase = 'Draft';

        $nuke = self::createStub(\Utilities\NukeCompat::class);
        $nuke->method('isUser')->willReturn(true);
        $nuke->method('cookieDecode')->willReturn([0 => '', 1 => 'testgm']);

        CsrfGuard::clearTokens('draft_selection');
        $_POST['_csrf_token'] = CsrfGuard::generateRawToken('draft_selection');

        return new DraftController(
            $this->db,
            $session,
            $season,
            new DraftValidator(),
            new DraftRepository($this->db, new TeamIdentityRepository($this->db)),
            new DraftProcessor(),
            new DraftView(),
            self::createStub(DraftServiceInterface::class),
            null,
            null,
            $nuke
        );
    }

    private function submit(int $round, int $pick, string $player): string
    {
        return $this->metrosController()->submitSelection(
            ['teamname' => 'Metros', 'player' => $player, 'draft_round' => (string) $round, 'draft_pick' => (string) $pick],
            'user-cookie'
        );
    }

    private function playerRowCount(string $name): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS c FROM ibl_plr WHERE name = ?');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        /** @var array{c: int} $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row['c'];
    }

    private function draftedPlayerInSlot(int $round, int $pick): string
    {
        $stmt = $this->db->prepare('SELECT player FROM ibl_draft WHERE year = ? AND round = ? AND pick = ?');
        $year = self::YEAR;
        $stmt->bind_param('iii', $year, $round, $pick);
        $stmt->execute();
        /** @var array{player: string} $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row['player'];
    }

    public function testOwnerSubmittingOwnSlotLandsThePick(): void
    {
        $this->insertDraftClassRow('OwnershipTest Own', 'PG');
        $this->insertDraftRow(self::YEAR, 1, 1, 1, '');
        $this->insertDraftPickRow(1, 1, self::YEAR, 1);   // ownerofpick defaults to Metros

        $result = $this->submit(1, 1, 'OwnershipTest Own');

        self::assertStringNotContainsString('You do not own this draft pick.', $result);
        self::assertSame('OwnershipTest Own', $this->draftedPlayerInSlot(1, 1));
        self::assertSame(1, $this->playerRowCount('OwnershipTest Own'));
    }

    public function testGmSubmittingAnotherTeamsSlotIsRejectedWithNoPlayerInsert(): void
    {
        $this->insertDraftClassRow('OwnershipTest Other', 'SG');
        $this->insertDraftRow(self::YEAR, 1, 2, 2, '', ['team' => 'Enforcers']);
        $this->insertDraftPickRow(2, 2, self::YEAR, 1, ['ownerofpick' => 'Enforcers', 'teampick' => 'Enforcers']);

        $result = $this->submit(1, 2, 'OwnershipTest Other');

        self::assertStringContainsString('You do not own this draft pick.', $result);
        self::assertSame('', $this->draftedPlayerInSlot(1, 2));
        self::assertSame(0, $this->playerRowCount('OwnershipTest Other'));
    }

    public function testTradedPickCurrentOwnerLandsThePick(): void
    {
        // Slot originated with team 2; ownership row says Metros holds it now.
        $this->insertDraftClassRow('OwnershipTest Traded', 'SF');
        $this->insertDraftRow(self::YEAR, 1, 3, 2, '', ['team' => 'Enforcers']);
        $this->insertDraftPickRow(1, 2, self::YEAR, 1, ['ownerofpick' => 'Metros', 'teampick' => 'Enforcers']);

        $result = $this->submit(1, 3, 'OwnershipTest Traded');

        self::assertStringNotContainsString('You do not own this draft pick.', $result);
        self::assertSame('OwnershipTest Traded', $this->draftedPlayerInSlot(1, 3));
        self::assertSame(1, $this->playerRowCount('OwnershipTest Traded'));
    }

    public function testTradedPickOriginalTeamIsRejectedWithNoPlayerInsert(): void
    {
        // Slot originated with Metros (teamid 1) but Metros traded it away.
        $this->insertDraftClassRow('OwnershipTest Gone', 'PF');
        $this->insertDraftRow(self::YEAR, 1, 4, 1, '');
        $this->insertDraftPickRow(2, 1, self::YEAR, 1, ['ownerofpick' => 'Enforcers', 'teampick' => 'Metros']);

        $result = $this->submit(1, 4, 'OwnershipTest Gone');

        self::assertStringContainsString('You do not own this draft pick.', $result);
        self::assertSame('', $this->draftedPlayerInSlot(1, 4));
        self::assertSame(0, $this->playerRowCount('OwnershipTest Gone'));
    }

    public function testOwnerRowForAnotherYearOnlyIsRejected(): void
    {
        // Ownership row exists for the previous draft year only: no owner for endingYear.
        $this->insertDraftClassRow('OwnershipTest Stale', 'C');
        $this->insertDraftRow(self::YEAR, 1, 5, 1, '');
        $this->insertDraftPickRow(1, 1, self::YEAR - 1, 1);

        $result = $this->submit(1, 5, 'OwnershipTest Stale');

        self::assertStringContainsString('You do not own this draft pick.', $result);
        self::assertSame(0, $this->playerRowCount('OwnershipTest Stale'));
    }
}
