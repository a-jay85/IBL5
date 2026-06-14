<?php

declare(strict_types=1);

namespace TradeBlock;

use Team\Contracts\TeamQueryRepositoryInterface;
use Team\Team;
use TradeBlock\Contracts\TradeBlockRepositoryInterface;
use TradeBlock\Contracts\TradeBlockServiceInterface;

/**
 * @see TradeBlockServiceInterface
 *
 * @phpstan-import-type BrowseData from TradeBlockServiceInterface
 * @phpstan-import-type EditFormData from TradeBlockServiceInterface
 * @phpstan-import-type AvailablePlayerRow from TradeBlockRepositoryInterface
 */
class TradeBlockService implements TradeBlockServiceInterface
{
    private TradeBlockRepositoryInterface $repo;
    private TeamQueryRepositoryInterface $teamQueryRepo;
    private \mysqli $db;

    public function __construct(
        TradeBlockRepositoryInterface $repo,
        TeamQueryRepositoryInterface $teamQueryRepo,
        \mysqli $db
    ) {
        $this->repo = $repo;
        $this->teamQueryRepo = $teamQueryRepo;
        $this->db = $db;
    }

    /**
     * @see TradeBlockServiceInterface::getBrowseData()
     *
     * @return BrowseData
     */
    public function getBrowseData(): array
    {
        $available = $this->repo->getAllAvailable();
        $seekingByTeam = $this->repo->getSeekingNotesByTeam();

        /** @var array<int, array{teamid: int, team_name: string, team_city: string, color1: string, color2: string, players: list<array{pid: int, name: string, note: string}>, seekingNote: string}> $groups */
        $groups = [];
        foreach ($available as $row) {
            $teamid = (int) $row['teamid'];
            if (!isset($groups[$teamid])) {
                $groups[$teamid] = [
                    'teamid' => $teamid,
                    'team_name' => (string) $row['team_name'],
                    'team_city' => (string) $row['team_city'],
                    'color1' => (string) $row['color1'],
                    'color2' => (string) $row['color2'],
                    'players' => [],
                    'seekingNote' => $seekingByTeam[$teamid] ?? '',
                ];
            }
            $groups[$teamid]['players'][] = [
                'pid' => (int) $row['pid'],
                'name' => (string) $row['name'],
                'note' => (string) $row['note'],
            ];
        }

        return ['teams' => array_values($groups)];
    }

    /**
     * @see TradeBlockServiceInterface::getEditFormData()
     *
     * @return EditFormData
     */
    public function getEditFormData(int $teamId): array
    {
        return [
            'team' => Team::initialize($this->db, $teamId),
            'roster' => $this->teamQueryRepo->getRosterUnderContractOrderedByName($teamId),
            'blockPids' => $this->repo->getBlockPidsForTeam($teamId),
            'seekingNote' => $this->repo->getSeekingNoteForTeam($teamId),
        ];
    }
}
