<?php

declare(strict_types=1);

namespace TradeBlock;

use Team\Contracts\TeamQueryRepositoryInterface;
use TradeBlock\Contracts\TradeBlockProcessorInterface;
use TradeBlock\Contracts\TradeBlockRepositoryInterface;

/**
 * @see TradeBlockProcessorInterface
 *
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 */
class TradeBlockProcessor implements TradeBlockProcessorInterface
{
    private TradeBlockRepositoryInterface $repo;
    private TeamQueryRepositoryInterface $teamQueryRepo;

    public function __construct(
        TradeBlockRepositoryInterface $repo,
        TeamQueryRepositoryInterface $teamQueryRepo
    ) {
        $this->repo = $repo;
        $this->teamQueryRepo = $teamQueryRepo;
    }

    /**
     * @see TradeBlockProcessorInterface::processEdit()
     *
     * @param list<int> $postedPids
     * @param array<int, string> $postedNotes
     * @return array{success: bool, result?: string, error?: string}
     */
    public function processEdit(int $resolvedTeamId, array $postedPids, array $postedNotes, string $seekingNote): array
    {
        // Authoritative roster for the team resolved server-side from the session.
        $roster = $this->teamQueryRepo->getRosterUnderContractOrderedByName($resolvedTeamId);

        /** @var list<int> $rosterPids */
        $rosterPids = [];
        foreach ($roster as $player) {
            /** @var PlayerRow $player */
            $rosterPids[] = (int) $player['pid'];
        }

        // IDOR defense: keep only submitted pids that are actually on this
        // team's roster. Forged cross-team pids never reach a write.
        $submitted = array_intersect($postedPids, $rosterPids);

        // Only ever touch block rows whose pid is on THIS team's roster.
        foreach ($rosterPids as $pid) {
            if (in_array($pid, $submitted, true)) {
                $note = $postedNotes[$pid] ?? '';
                $this->repo->setOnBlock($pid, $note);
            } else {
                $this->repo->removeFromBlock($pid);
            }
        }

        $this->repo->upsertSeekingNote($resolvedTeamId, $seekingNote);

        return ['success' => true, 'result' => 'block_updated'];
    }
}
