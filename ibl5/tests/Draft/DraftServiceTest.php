<?php

declare(strict_types=1);

namespace Tests\Draft;

use PHPUnit\Framework\TestCase;
use Draft\DraftService;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Season\Season;
use Tests\WideUnit\Mocks\MockDatabase;

class DraftServiceTest extends TestCase
{
    private MockDatabase $mockDb;
    /** @var TeamIdentityRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private TeamIdentityRepositoryInterface $mockCommonRepository;
    /** @var Season&\PHPUnit\Framework\MockObject\Stub */
    private Season $mockSeason;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $this->mockDb;
        $this->mockCommonRepository = self::createStub(TeamIdentityRepositoryInterface::class);
        $this->mockSeason = self::createStub(Season::class);
        $this->mockSeason->endingYear = 2025;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    public function testGetDraftBoardDataWithOwnerFound(): void
    {
        $this->mockCommonRepository->method('getTeamnameFromUsername')->willReturn('Test Team');
        $this->mockCommonRepository->method('getTidFromTeamname')->willReturn(5);

        // Route current pick query first (most specific), then owner, then draft class
        $this->mockDb->onQuery("AND player = ''", [
            ['team' => 'X', 'teamid' => 3, 'round' => 1, 'pick' => 5],
        ]);
        $this->mockDb->onQuery('ibl_draft_picks', [
            ['ownerofpick' => 'Owner Team'],
        ]);
        $this->mockDb->onQuery('ibl_draft_class', []);

        $service = new DraftService($this->mockDb, $this->mockCommonRepository, $this->mockSeason);
        $data = $service->getDraftBoardData('testgm');

        $this->assertSame('Owner Team', $data->pickOwner);
        $this->assertSame(1, $data->draftRound);
        $this->assertSame(5, $data->draftPick);
        $this->assertSame(5, $data->teamId);
    }

    public function testGetDraftBoardDataWithDraftTidZeroSkipsOwnerLookup(): void
    {
        $this->mockCommonRepository->method('getTeamnameFromUsername')->willReturn('Test Team');
        $this->mockCommonRepository->method('getTidFromTeamname')->willReturn(3);

        $this->mockDb->onQuery("AND player = ''", [
            ['team' => 'X', 'teamid' => 0, 'round' => 2, 'pick' => 8],
        ]);
        $this->mockDb->onQuery('ibl_draft_class', []);

        $service = new DraftService($this->mockDb, $this->mockCommonRepository, $this->mockSeason);
        $data = $service->getDraftBoardData('testgm');

        $this->assertNull($data->pickOwner);
        $this->assertSame(2, $data->draftRound);
        $this->assertSame(8, $data->draftPick);
    }

    public function testGetDraftBoardDataWithNoCurrentPick(): void
    {
        $this->mockCommonRepository->method('getTeamnameFromUsername')->willReturn('Test Team');
        $this->mockCommonRepository->method('getTidFromTeamname')->willReturn(3);

        $this->mockDb->onQuery("AND player = ''", []);
        $this->mockDb->onQuery('ibl_draft_class', []);

        $service = new DraftService($this->mockDb, $this->mockCommonRepository, $this->mockSeason);
        $data = $service->getDraftBoardData('testgm');

        $this->assertNull($data->pickOwner);
        $this->assertNull($data->draftRound);
        $this->assertNull($data->draftPick);
    }

    public function testGetDraftBoardDataWithNullTidFromTeamname(): void
    {
        $this->mockCommonRepository->method('getTeamnameFromUsername')->willReturn('Test Team');
        $this->mockCommonRepository->method('getTidFromTeamname')->willReturn(null);

        $this->mockDb->onQuery("AND player = ''", []);
        $this->mockDb->onQuery('ibl_draft_class', []);

        $service = new DraftService($this->mockDb, $this->mockCommonRepository, $this->mockSeason);
        $data = $service->getDraftBoardData('testgm');

        $this->assertSame(0, $data->teamId);
    }

    public function testGetDraftBoardDataPassesSeasonEndingYearToCurrentPickLookup(): void
    {
        $this->mockCommonRepository->method('getTeamnameFromUsername')->willReturn('Test Team');
        $this->mockCommonRepository->method('getTidFromTeamname')->willReturn(1);

        $this->mockDb->onQuery("AND player = ''", []);
        $this->mockDb->onQuery('ibl_draft_class', []);

        $service = new DraftService($this->mockDb, $this->mockCommonRepository, $this->mockSeason);
        $service->getDraftBoardData('testuser');

        $queries = $this->mockDb->getExecutedQueries();
        $pickQuery = '';
        foreach ($queries as $q) {
            if (str_contains($q, "player = ''")) {
                $pickQuery = $q;
                break;
            }
        }
        self::assertNotEmpty($pickQuery, 'getCurrentDraftPick query must be executed');
        self::assertStringContainsString('2025', $pickQuery, 'getCurrentDraftPick must use Season::endingYear (2025)');
    }
}
