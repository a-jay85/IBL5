<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use JsbParser\Repositories\AswRepository;
use PHPUnit\Framework\Attributes\Group;

/**
 * DB-integration tests for the stale-roster finder, the conference lookup, and the
 * family-guarded award insert on AswRepository.
 *
 * Seasons 2089 and 2090 never collide with ci-seed rows; every fixture rolls back
 * with the DatabaseTestCase transaction.
 */
#[Group('database')]
class AswRepositoryAllStarAwardsTest extends DatabaseTestCase
{
    private const PID = 200000401;
    private const HIST_YEAR = 2090;
    private const EVENT_TYPES = ['allstar_1', 'allstar_2', 'rookie_1', 'rookie_2', 'three_point', 'dunk_contest'];

    private AswRepository $repo;
    private int $nextSlot = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new AswRepository($this->db);
    }

    public function testFindOtherSeasonReturnsMatchingEarlierYear(): void
    {
        $rosters = $this->sampleRosters();
        $this->seedRosters(2089, $rosters);

        self::assertSame(2089, $this->repo->findOtherSeasonWithIdenticalRosters(2090, $rosters));
    }

    public function testFindOtherSeasonIgnoresTargetSeason(): void
    {
        $rosters = $this->sampleRosters();
        $this->seedRosters(2090, $rosters);

        self::assertNull($this->repo->findOtherSeasonWithIdenticalRosters(2090, $rosters));
    }

    public function testFindOtherSeasonReturnsNullWhenOneSlotDiffers(): void
    {
        $rosters = $this->sampleRosters();
        $this->seedRosters(2089, $rosters);

        $differing = $rosters;
        $differing['dunk_contest'] = [200000301, 200000399];

        self::assertNull($this->repo->findOtherSeasonWithIdenticalRosters(2090, $differing));
    }

    public function testFindConferencePrefersHistTeam(): void
    {
        $this->insertTestPlayer(self::PID, 'Asw Conf Player', ['teamid' => 2]);
        $this->insertHistRow(self::PID, 'Asw Conf Player', self::HIST_YEAR, ['teamid' => 1]);
        $this->seedConfig(2090, 1, 'Eastern');
        $this->seedConfig(2090, 2, 'Western');

        self::assertSame('Eastern', $this->repo->findAllStarConference(self::PID, 2090));
    }

    public function testFindConferenceFallsBackToPlrTeam(): void
    {
        $this->insertTestPlayer(self::PID, 'Asw Conf Player', ['teamid' => 2]);
        $this->seedConfig(2090, 1, 'Eastern');
        $this->seedConfig(2090, 2, 'Western');

        self::assertSame('Western', $this->repo->findAllStarConference(self::PID, 2090));
    }

    public function testFindConferenceReturnsNullForUnmappedTeam(): void
    {
        $this->insertTestPlayer(self::PID, 'Asw Conf Player', ['teamid' => 3]);
        $this->seedConfig(2090, 1, 'Eastern');
        $this->seedConfig(2090, 2, 'Western');

        self::assertNull($this->repo->findAllStarConference(self::PID, 2090));
    }

    public function testInsertAwardInsertsWhenAbsent(): void
    {
        $affected = $this->repo->insertAwardUnlessFamilyExists(
            2090,
            'Eastern Conference All-Star',
            'Asw Test',
            'Western Conference All-Star'
        );

        self::assertSame(1, $affected);
        self::assertSame(1, $this->awardCount('Asw Test'));
    }

    public function testInsertAwardSkipsExactDuplicate(): void
    {
        $this->repo->insertAwardUnlessFamilyExists(2090, 'Eastern Conference All-Star', 'Asw Test', 'Western Conference All-Star');

        $affected = $this->repo->insertAwardUnlessFamilyExists(
            2090,
            'Eastern Conference All-Star',
            'Asw Test',
            'Western Conference All-Star'
        );

        self::assertSame(0, $affected);
        self::assertSame(1, $this->awardCount('Asw Test'));
    }

    public function testInsertAwardSkipsWhenSiblingFamilyRowExists(): void
    {
        $this->insertRow('ibl_awards', [
            'year' => 2090,
            'award' => 'Western Conference All-Star',
            'name' => 'Asw Test',
        ]);

        $affected = $this->repo->insertAwardUnlessFamilyExists(
            2090,
            'Eastern Conference All-Star',
            'Asw Test',
            'Western Conference All-Star'
        );

        self::assertSame(0, $affected);
        self::assertSame(1, $this->awardCount('Asw Test'));
        $result = $this->db->query(
            "SELECT COUNT(*) AS c FROM ibl_awards WHERE name = 'Asw Test' AND award = 'Eastern Conference All-Star'"
        );
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $result->free();
        self::assertNotNull($row);
        self::assertSame(0, (int) $row['c']);
    }

    /**
     * @return array<string, list<int>>
     */
    private function sampleRosters(): array
    {
        return [
            'allstar_1' => [200000101, 200000102],
            'allstar_2' => [200000111],
            'rookie_1' => [200000201],
            'rookie_2' => [200000211],
            'three_point' => [200000301, 200000302],
            'dunk_contest' => [200000301, 200000302],
        ];
    }

    /**
     * @param array<string, list<int>> $rosters
     */
    private function seedRosters(int $seasonYear, array $rosters): void
    {
        foreach (self::EVENT_TYPES as $eventType) {
            foreach ($rosters[$eventType] as $index => $pid) {
                $this->insertRow('ibl_jsb_allstar_rosters', [
                    'season_year' => $seasonYear,
                    'event_type' => $eventType,
                    'roster_slot' => $index + 1,
                    'pid' => $pid,
                ]);
            }
        }
    }

    private function seedConfig(int $seasonEndingYear, int $teamId, string $conference): void
    {
        $this->insertRow('ibl_league_config', [
            'season_ending_year' => $seasonEndingYear,
            'team_slot' => $this->nextSlot++,
            'team_name' => 'Asw Config Team ' . $teamId,
            'teamid' => $teamId,
            'conference' => $conference,
            'division' => 'Atlantic',
            'playoff_qualifiers_per_conf' => 4,
            'playoff_round1_format' => 'bo7',
            'playoff_round2_format' => 'bo7',
            'playoff_round3_format' => 'bo7',
            'playoff_round4_format' => 'bo7',
            'team_count' => 2,
        ]);
    }

    private function awardCount(string $name): int
    {
        $result = $this->db->query("SELECT COUNT(*) AS c FROM ibl_awards WHERE year = 2090 AND name = '" . $this->db->real_escape_string($name) . "'");
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $result->free();
        self::assertNotNull($row);
        return (int) $row['c'];
    }
}
