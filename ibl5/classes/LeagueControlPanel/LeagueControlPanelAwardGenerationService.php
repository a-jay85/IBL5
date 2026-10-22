<?php

declare(strict_types=1);

namespace LeagueControlPanel;

use JsbParser\LeadersHtmParser;
use LeagueControlPanel\Contracts\LeagueControlPanelAwardGenerationServiceInterface;
use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use Voting\Contracts\VotingResultsServiceInterface;
use Voting\VotingRepository;
use Voting\VotingResultsService;
/**
 * Orchestrates generation of season awards from votes and JSB Leaders.htm data.
 *
 * @see LeagueControlPanelAwardGenerationServiceInterface
 */
class LeagueControlPanelAwardGenerationService implements LeagueControlPanelAwardGenerationServiceInterface
{
    /** @var array<string, string> Maps vote category titles to DB award prefixes */
    private const VOTE_AWARD_MAP = [
        'Most Valuable Player' => 'Most Valuable Player',
        'Sixth Man of the Year' => '6th Man Award',
        'Rookie of the Year' => 'Rookie of the Year',
    ];

    /** @var list<string> Rank suffixes for individual awards (1st through 5th) */
    private const RANK_SUFFIXES = ['(1st)', '(2nd)', '(3rd)', '(4th)', '(5th)'];

    /** @var array<string, list<string>> Maps vote category to team award names for fill logic */
    private const TEAM_FROM_VOTES = [
        'Most Valuable Player' => [
            'All-League First Team',
            'All-League Second Team',
            'All-League Third Team',
        ],
        'Rookie of the Year' => [
            'All-Rookie Team (1st)',
            'All-Rookie Team (2nd)',
            'All-Rookie Team (3rd)',
        ],
    ];

    private LeagueControlPanelRepositoryInterface $repository;
    private VotingResultsServiceInterface $votingResultsService;

    public function __construct(
        LeagueControlPanelRepositoryInterface $repository,
        VotingResultsServiceInterface $votingResultsService,
    ) {
        $this->repository = $repository;
        $this->votingResultsService = $votingResultsService;
    }

    /**
     * @see LeagueControlPanelAwardGenerationServiceInterface::generateSeasonAwards()
     */
    public function generateSeasonAwards(int $year, string $leadersHtmPath): array
    {
        try {
            $leadersData = LeadersHtmParser::parseFile($leadersHtmPath);
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => 'Failed to parse Leaders.htm: ' . $e->getMessage(),
                'inserted' => 0,
                'skipped' => 0,
            ];
        }

        $voteResults = $this->votingResultsService->getEndOfYearResults();
        /** @var array<string, list<array{name: string, votes: int, pid: int}>> $votesByCategory */
        $votesByCategory = [];
        foreach ($voteResults as $category) {
            $votesByCategory[$category['title']] = $category['rows'];
        }

        /** @var list<array{0: int, 1: int}> $counts */
        $counts = [];
        $counts[] = $this->insertVoteAwards($year, $votesByCategory);
        $counts[] = $this->insertGmOfTheYear($year, $votesByCategory);
        $counts[] = $this->insertDefensivePlayerOfTheYear($year, $leadersData['individual']);
        $counts[] = $this->insertStatLeaders($year, $leadersData['stat_leaders']);
        $counts[] = $this->insertAllLeagueTeams($year, $votesByCategory, $leadersData['teams']);
        $counts[] = $this->insertAllRookieTeams($year, $votesByCategory, $leadersData['teams']);
        $counts[] = $this->insertAllDefensiveTeams($year, $leadersData['teams']);

        $inserted = array_sum(array_column($counts, 0));
        $skipped = array_sum(array_column($counts, 1));
        $total = $inserted + $skipped;
        return [
            'success' => true,
            'message' => "Season awards generated: {$inserted} inserted, {$skipped} already existed. Total: {$total}.",
            'inserted' => $inserted,
            'skipped' => $skipped,
        ];
    }

    /**
     * Step 1: individual awards from votes (MVP, 6th Man, ROY, top 5 each).
     *
     * @param array<string, list<array{name: string, votes: int, pid: int}>> $votesByCategory
     * @return array{0: int, 1: int} [inserted, skipped]
     */
    private function insertVoteAwards(int $year, array $votesByCategory): array
    {
        $inserted = 0;
        $skipped = 0;
        foreach (self::VOTE_AWARD_MAP as $voteTitle => $dbPrefix) {
            $voters = $votesByCategory[$voteTitle] ?? [];
            [$i, $s] = $this->insertRankedAwards($year, $dbPrefix, $this->extractNames($voters, 5));
            $inserted += $i;
            $skipped += $s;
        }
        return [$inserted, $skipped];
    }

    /**
     * Step 2: GM of the Year from votes (top 1 into ibl_gm_awards).
     *
     * @param array<string, list<array{name: string, votes: int, pid: int}>> $votesByCategory
     * @return array{0: int, 1: int} [inserted, skipped]
     */
    private function insertGmOfTheYear(int $year, array $votesByCategory): array
    {
        $gmVoters = $votesByCategory['GM of the Year'] ?? [];
        if ($gmVoters === []) {
            return [0, 0];
        }
        $gmName = VotingResultsService::extractPlayerName($gmVoters[0]['name']);
        $affected = $this->repository->upsertGmAward($year, $gmName);
        return $affected === 1 ? [1, 0] : [0, 1];
    }

    /**
     * Step 3: Defensive Player of the Year from Leaders.htm (5 players).
     *
     * @param array<string, list<string>> $individual
     * @return array{0: int, 1: int} [inserted, skipped]
     */
    private function insertDefensivePlayerOfTheYear(int $year, array $individual): array
    {
        return $this->insertRankedAwards($year, 'Defensive Player of the Year', $individual['Defensive Player of the Year'] ?? []);
    }

    /**
     * Step 4: stat leaders from Leaders.htm (5 categories x 5 ranks).
     *
     * @param array<string, list<array{name: string, stat: string}>> $statLeaders
     * @return array{0: int, 1: int} [inserted, skipped]
     */
    private function insertStatLeaders(int $year, array $statLeaders): array
    {
        $inserted = 0;
        $skipped = 0;
        foreach ($statLeaders as $category => $leaders) {
            $names = array_map(
                static fn (array $leader): string => $leader['name'],
                $leaders
            );
            [$i, $s] = $this->insertRankedAwards($year, $category, $names);
            $inserted += $i;
            $skipped += $s;
        }
        return [$inserted, $skipped];
    }

    /**
     * Step 5: All-League Teams (votes + JSB fill).
     *
     * @param array<string, list<array{name: string, votes: int, pid: int}>> $votesByCategory
     * @param array<string, list<string>> $jsbTeams
     * @return array{0: int, 1: int} [inserted, skipped]
     */
    private function insertAllLeagueTeams(int $year, array $votesByCategory, array $jsbTeams): array
    {
        $mvpVoters = $votesByCategory['Most Valuable Player'] ?? [];
        return $this->insertTeamAwards(
            $year,
            $this->extractNames($mvpVoters, count($mvpVoters)),
            self::TEAM_FROM_VOTES['Most Valuable Player'],
            $jsbTeams,
        );
    }

    /**
     * Step 6: All-Rookie Teams (votes + JSB fill).
     *
     * @param array<string, list<array{name: string, votes: int, pid: int}>> $votesByCategory
     * @param array<string, list<string>> $jsbTeams
     * @return array{0: int, 1: int} [inserted, skipped]
     */
    private function insertAllRookieTeams(int $year, array $votesByCategory, array $jsbTeams): array
    {
        $royVoters = $votesByCategory['Rookie of the Year'] ?? [];
        return $this->insertTeamAwards(
            $year,
            $this->extractNames($royVoters, count($royVoters)),
            self::TEAM_FROM_VOTES['Rookie of the Year'],
            $jsbTeams,
        );
    }

    /**
     * Step 7: All-Defensive Teams (entirely from Leaders.htm).
     *
     * @param array<string, list<string>> $jsbTeams
     * @return array{0: int, 1: int} [inserted, skipped]
     */
    private function insertAllDefensiveTeams(int $year, array $jsbTeams): array
    {
        $inserted = 0;
        $skipped = 0;
        $defTeamNames = [
            'All-Defensive Team (1st)',
            'All-Defensive Team (2nd)',
            'All-Defensive Team (3rd)',
        ];
        foreach ($defTeamNames as $teamName) {
            $players = $jsbTeams[$teamName] ?? [];
            foreach ($players as $name) {
                $affected = $this->repository->upsertAward($year, $teamName, $name);
                if ($affected === 1) {
                    $inserted++;
                } else {
                    $skipped++;
                }
            }
        }
        return [$inserted, $skipped];
    }

    /**
     * Insert ranked individual awards (e.g., "MVP (1st)" through "(5th)").
     *
     * @param list<string> $names Player names in rank order
     * @return array{0: int, 1: int} [inserted, skipped]
     */
    private function insertRankedAwards(int $year, string $prefix, array $names): array
    {
        $inserted = 0;
        $skipped = 0;
        $maxRanks = min(count($names), 5);
        for ($i = 0; $i < $maxRanks; $i++) {
            $awardName = $prefix . ' ' . self::RANK_SUFFIXES[$i];
            $affected = $this->repository->upsertAward($year, $awardName, $names[$i]);
            if ($affected === 1) {
                $inserted++;
            } else {
                $skipped++;
            }
        }
        return [$inserted, $skipped];
    }

    /**
     * Insert team awards using vote-getters first, then filling from Leaders.htm.
     *
     * @param list<string> $voteGetters All vote-getters in ranked order
     * @param list<string> $teamNames DB team award names [1st, 2nd, 3rd]
     * @param array<string, list<string>> $jsbTeams Leaders.htm team data
     * @return array{0: int, 1: int} [inserted, skipped]
     */
    private function insertTeamAwards(
        int $year,
        array $voteGetters,
        array $teamNames,
        array $jsbTeams,
    ): array {
        $inserted = 0;
        $skipped = 0;
        /** @var array<string, true> $assigned */
        $assigned = [];
        $teamSize = 5;

        // Build teams from vote-getters
        /** @var list<list<string>> $teams */
        $teams = [[], [], []];

        foreach ($voteGetters as $index => $name) {
            $teamIndex = intdiv($index, $teamSize);
            if ($teamIndex >= 3) {
                break;
            }
            if (count($teams[$teamIndex]) >= $teamSize) {
                continue;
            }
            $teams[$teamIndex][] = $name;
            $assigned[$name] = true;
        }

        // Fill remaining spots from Leaders.htm
        for ($t = 0; $t < 3; $t++) {
            if (count($teams[$t]) >= $teamSize) {
                continue;
            }

            $jsbPlayers = $jsbTeams[$teamNames[$t]] ?? [];
            foreach ($jsbPlayers as $name) {
                if (isset($assigned[$name])) {
                    continue;
                }
                $teams[$t][] = $name;
                $assigned[$name] = true;
                if (count($teams[$t]) >= $teamSize) {
                    break;
                }
            }
        }

        // Insert all team members
        for ($t = 0; $t < 3; $t++) {
            foreach ($teams[$t] as $name) {
                $affected = $this->repository->upsertAward($year, $teamNames[$t], $name);
                if ($affected === 1) {
                    $inserted++;
                } else {
                    $skipped++;
                }
            }
        }
        return [$inserted, $skipped];
    }

    /**
     * Extract player names from vote rows, filtering out blank ballots.
     *
     * Vote names are stored as "Player Name, Team" — extract just the player name.
     *
     * @param list<array{name: string, votes: int, pid: int}> $voters
     * @return list<string>
     */
    private function extractNames(array $voters, int $limit): array
    {
        $names = [];
        foreach ($voters as $voter) {
            if ($voter['name'] === VotingRepository::BLANK_BALLOT_LABEL) {
                continue;
            }
            $names[] = VotingResultsService::extractPlayerName($voter['name']);
            if (count($names) >= $limit) {
                break;
            }
        }
        return $names;
    }

}
