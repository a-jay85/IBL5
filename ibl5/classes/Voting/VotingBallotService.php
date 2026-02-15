<?php

declare(strict_types=1);

namespace Voting;

use Player\Player;
use Player\PlayerStats;
use Voting\Contracts\VotingBallotServiceInterface;
use Voting\Contracts\VotingBallotViewInterface;

/**
 * VotingBallotService - Assembles ballot candidate data for voting
 *
 * @phpstan-import-type BallotCategory from VotingBallotViewInterface
 *
 * @see VotingBallotServiceInterface For the interface contract
 */
class VotingBallotService implements VotingBallotServiceInterface
{
    private \mysqli $db;

    /**
     * @param \mysqli $db Database connection for Player/PlayerStats instantiation
     */
    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * @see VotingBallotServiceInterface::getBallotData()
     *
     * @return list<BallotCategory>
     */
    public function getBallotData(
        string $voterTeamName,
        \Season $season,
        \League $league
    ): array {
        if ($season->phase === 'Regular Season') {
            return $this->getAllStarBallotData($league);
        }

        return $this->getEndOfYearBallotData($league);
    }

    /**
     * Get All-Star Game ballot categories
     *
     * @return list<BallotCategory>
     */
    private function getAllStarBallotData(\League $league): array
    {
        return [
            $this->buildPlayerCategory('ECF', 'Eastern Conference Frontcourt', 'Select FOUR players. Tap/click to reveal/hide nominees.', $league->getAllStarCandidatesResult('ECF')),
            $this->buildPlayerCategory('ECB', 'Eastern Conference Backcourt', 'Select FOUR players. Tap/click to reveal/hide nominees.', $league->getAllStarCandidatesResult('ECB')),
            $this->buildPlayerCategory('WCF', 'Western Conference Frontcourt', 'Select FOUR players. Tap/click to reveal/hide nominees.', $league->getAllStarCandidatesResult('WCF')),
            $this->buildPlayerCategory('WCB', 'Western Conference Backcourt', 'Select FOUR players. Tap/click to reveal/hide nominees.', $league->getAllStarCandidatesResult('WCB')),
        ];
    }

    /**
     * Get end-of-year award ballot categories
     *
     * @return list<BallotCategory>
     */
    private function getEndOfYearBallotData(\League $league): array
    {
        return [
            $this->buildPlayerCategory('MVP', 'Most Valuable Player', 'Select your top THREE choices. Tap/click to reveal/hide nominees.', $league->getMVPCandidatesResult()),
            $this->buildPlayerCategory('Six', 'Sixth-Person of the Year', 'Select your top THREE choices. Tap/click to reveal/hide nominees.', $league->getSixthPersonOfTheYearCandidatesResult()),
            $this->buildPlayerCategory('ROY', 'Rookie of the Year', 'Select your top THREE choices. Tap/click to reveal/hide nominees.', $league->getRookieOfTheYearCandidatesResult()),
            $this->buildGMCategory($league->getGMOfTheYearCandidatesResult()),
        ];
    }

    /**
     * Build a player category with stats
     *
     * @param string $code Category code
     * @param string $title Display title
     * @param string $instruction Voter instruction
     * @param array<int, array<string, mixed>> $rows Raw player rows
     * @return BallotCategory
     */
    private function buildPlayerCategory(string $code, string $title, string $instruction, array $rows): array
    {
        $candidates = [];
        foreach ($rows as $row) {
            /** @phpstan-ignore argument.type (League methods return full PlayerRow arrays from SELECT * queries) */
            $player = Player::withPlrRow($this->db, $row);
            $playerStats = PlayerStats::withPlrRow($this->db, $row);
            $candidates[] = [
                'type' => 'player',
                'name' => $player->name,
                'teamName' => $player->teamName,
                'playerID' => $player->playerID,
                'stats' => $playerStats,
            ];
        }

        return [
            'code' => $code,
            'title' => $title,
            'instruction' => $instruction,
            'candidates' => $candidates,
        ];
    }

    /**
     * Build the GM of the Year category
     *
     * @param array<int, array<string, mixed>> $rows Raw GM rows
     * @return BallotCategory
     */
    private function buildGMCategory(array $rows): array
    {
        $candidates = [];
        foreach ($rows as $row) {
            $name = is_string($row['owner_name']) ? $row['owner_name'] : '';
            $teamCity = is_string($row['team_city']) ? $row['team_city'] : '';
            $teamName = is_string($row['team_name']) ? $row['team_name'] : '';
            $teamname = trim($teamCity . ' ' . $teamName);
            $candidates[] = [
                'type' => 'gm',
                'name' => $name,
                'teamName' => $teamname,
            ];
        }

        return [
            'code' => 'GM',
            'title' => 'General Manager of the Year',
            'instruction' => 'Select your top THREE choices. Tap/click to reveal/hide nominees.',
            'candidates' => $candidates,
        ];
    }
}
