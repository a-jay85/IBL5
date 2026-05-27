<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use Voting\VotingRepository;

#[Group('database')]
class VotingRepositoryTest extends DatabaseTestCase
{
    private VotingRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new VotingRepository($this->db);
    }

    public function testSaveEoyVoteUpdatesRow(): void
    {
        $ballot = [
            'mvp_1' => 'Test Player One, Metros',
            'mvp_2' => 'Test Player Two, Stars',
            'mvp_3' => 'Test Player Three, Stars',
            'six_1' => 'Test Player Four, Stars',
            'six_2' => '', 'six_3' => '',
            'roy_1' => '', 'roy_2' => '', 'roy_3' => '',
            'gm_1' => '', 'gm_2' => '', 'gm_3' => '',
        ];

        $this->repo->saveEoyVote('Metros', $ballot);

        $row = $this->fetchRow('ibl_votes_EOY', 'team_name', 'Metros');
        self::assertSame('Test Player One, Metros', $row['mvp_1']);
        self::assertSame('Test Player Two, Stars', $row['mvp_2']);
        self::assertSame('Test Player Three, Stars', $row['mvp_3']);
        self::assertSame('Test Player Four, Stars', $row['six_1']);
    }

    public function testSaveAsgVoteUpdatesRow(): void
    {
        $ballot = [
            'east_f1' => 'Test Player One, Metros',
            'east_f2' => 'Test Player Three, Stars',
            'east_f3' => '', 'east_f4' => '',
            'east_b1' => '', 'east_b2' => '', 'east_b3' => '', 'east_b4' => '',
            'west_f1' => 'Test Player Five, Cougars',
            'west_f2' => '', 'west_f3' => '', 'west_f4' => '',
            'west_b1' => '', 'west_b2' => '', 'west_b3' => '', 'west_b4' => '',
        ];

        $this->repo->saveAsgVote('Stars', $ballot);

        $row = $this->fetchRow('ibl_votes_ASG', 'team_name', 'Stars');
        self::assertSame('Test Player One, Metros', $row['east_f1']);
        self::assertSame('Test Player Three, Stars', $row['east_f2']);
        self::assertSame('Test Player Five, Cougars', $row['west_f1']);
    }

    public function testMarkEoyVoteCastSetsTimestamp(): void
    {
        $this->repo->markEoyVoteCast('Metros');

        $row = $this->fetchRow('ibl_team_info', 'team_name', 'Metros');
        self::assertNotSame('No Vote', $row['eoy_vote']);
    }

    public function testMarkAsgVoteCastSetsTimestamp(): void
    {
        $this->repo->markAsgVoteCast('Stars');

        $row = $this->fetchRow('ibl_team_info', 'team_name', 'Stars');
        self::assertNotSame('No Vote', $row['asg_vote']);
    }

    public function testFetchAllStarTotalsAggregatesVotesAcrossColumns(): void
    {
        $this->setAsgVote('Metros', 'east_f1', 'Test Player One, Metros');
        $this->setAsgVote('Metros', 'east_f2', 'Test Player Three, Stars');
        $this->setAsgVote('Stars', 'east_f1', 'Test Player One, Metros');
        $this->setAsgVote('Stars', 'east_f2', 'Test Player One, Metros');

        $results = $this->repo->fetchAllStarTotals(['east_f1', 'east_f2']);

        $byName = $this->indexByName($results);
        self::assertSame(3, $byName['Test Player One, Metros']['votes']);
        self::assertSame(1, $byName['Test Player Three, Stars']['votes']);
    }

    public function testFetchAllStarTotalsExcludesBlankEntries(): void
    {
        $this->setAsgVote('Metros', 'east_f1', 'Test Player One, Metros');
        $this->setAsgVote('Metros', 'east_f2', '');
        $this->setAsgVote('Stars', 'east_f1', '');
        $this->setAsgVote('Stars', 'east_f2', '');

        $results = $this->repo->fetchAllStarTotals(['east_f1', 'east_f2']);

        $names = array_column($results, 'name');
        self::assertNotContains('', $names);
        self::assertCount(1, array_filter($results, static fn (array $r): bool => $r['name'] !== VotingRepository::BLANK_BALLOT_LABEL));
    }

    public function testFetchEndOfYearTotalsAppliesWeightedScoring(): void
    {
        $this->setEoyVote('Metros', 'mvp_1', 'Test Player One, Metros');
        $this->setEoyVote('Stars', 'mvp_2', 'Test Player One, Metros');
        $this->setEoyVote('Metros', 'mvp_2', 'Test Player Three, Stars');

        $results = $this->repo->fetchEndOfYearTotals([
            'mvp_1' => 5,
            'mvp_2' => 3,
        ]);

        $byName = $this->indexByName($results);
        self::assertSame(8, $byName['Test Player One, Metros']['votes']);
        self::assertSame(3, $byName['Test Player Three, Stars']['votes']);
    }

    public function testFetchPlayerIdsByNamesReturnsPidMap(): void
    {
        $result = $this->repo->fetchPlayerIdsByNames(['Test Player One', 'Test Player Three']);

        self::assertSame(1, $result['Test Player One']);
        self::assertSame(3, $result['Test Player Three']);
    }

    public function testFetchPlayerIdsByNamesReturnsEmptyForNoMatches(): void
    {
        $result = $this->repo->fetchPlayerIdsByNames(['Nonexistent Player']);
        self::assertSame([], $result);
    }

    public function testValidateColumnsRejectsInvalidColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid vote column: bobby_tables');

        $this->repo->fetchAllStarTotals(['bobby_tables']);
    }

    public function testFetchAllStarTotalsResolvesPlayerIds(): void
    {
        $this->setAsgVote('Metros', 'east_f1', 'Test Player One, Metros');

        $results = $this->repo->fetchAllStarTotals(['east_f1']);

        self::assertCount(1, $results);
        self::assertSame(1, $results[0]['pid']);
    }

    // ==================== Helpers ====================

    private function fetchRow(string $table, string $keyColumn, string $keyValue): array
    {
        $stmt = $this->db->prepare("SELECT * FROM `{$table}` WHERE `{$keyColumn}` = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $keyValue);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        self::assertIsArray($row);

        return $row;
    }

    private function setAsgVote(string $teamName, string $column, string $value): void
    {
        $stmt = $this->db->prepare("UPDATE `ibl_votes_ASG` SET `{$column}` = ? WHERE team_name = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('ss', $value, $teamName);
        $stmt->execute();
        $stmt->close();
    }

    private function setEoyVote(string $teamName, string $column, string $value): void
    {
        $stmt = $this->db->prepare("UPDATE `ibl_votes_EOY` SET `{$column}` = ? WHERE team_name = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('ss', $value, $teamName);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * @param list<array{name: string, votes: int, pid: int}> $rows
     * @return array<string, array{name: string, votes: int, pid: int}>
     */
    private function indexByName(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[$row['name']] = $row;
        }
        return $result;
    }
}
