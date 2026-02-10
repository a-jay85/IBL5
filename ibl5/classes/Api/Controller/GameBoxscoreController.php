<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Cache\ETagHandler;
use Api\Contracts\ControllerInterface;
use Api\Repository\ApiGameRepository;
use Api\Response\JsonResponder;
use Api\Transformer\BoxscoreTransformer;
use Api\Transformer\GameTransformer;

class GameBoxscoreController implements ControllerInterface
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
        $uuid = $params['uuid'] ?? '';
        $repo = new ApiGameRepository($this->db);
        $gameTransformer = new GameTransformer();
        $boxscoreTransformer = new BoxscoreTransformer();
        $etag = new ETagHandler();

        $game = $repo->getGameByUuid($uuid);
        if ($game === null) {
            $responder->error(404, 'not_found', 'Game not found.');
            return;
        }

        $gameStatus = is_string($game['game_status'] ?? null) ? $game['game_status'] : '';
        if ($gameStatus === 'scheduled') {
            $responder->error(404, 'no_boxscore', 'Box score is not available for scheduled games.');
            return;
        }

        $visitorTeamId = is_int($game['visitor_team_id'] ?? null) ? $game['visitor_team_id'] : 0;
        $homeTeamId = is_int($game['home_team_id'] ?? null) ? $game['home_team_id'] : 0;
        $gameDate = is_string($game['game_date'] ?? null) ? $game['game_date'] : '';

        $teamRows = $repo->getBoxscoreTeams($visitorTeamId, $homeTeamId, $gameDate);
        $playerRows = $repo->getBoxscorePlayers($visitorTeamId, $homeTeamId, $gameDate);

        $updatedAt = is_string($game['updated_at'] ?? null) ? $game['updated_at'] : '';
        $tag = $etag->generate($updatedAt);
        if ($etag->matches($tag)) {
            $responder->notModified();
            return;
        }

        /** @phpstan-ignore argument.type (DB view guarantees array shape) */
        $gameData = $gameTransformer->transform($game);

        $visitorTeamStats = null;
        $homeTeamStats = null;
        $visitorPlayers = [];
        $homePlayers = [];

        foreach ($teamRows as $teamRow) {
            /** @phpstan-ignore argument.type (DB row guarantees array shape) */
            $transformed = $boxscoreTransformer->transformTeamStats($teamRow);
            if ($visitorTeamStats === null) {
                $visitorTeamStats = $transformed;
            } else {
                $homeTeamStats = $transformed;
            }
        }

        foreach ($playerRows as $playerRow) {
            $playerTid = is_int($playerRow['player_tid'] ?? null) ? $playerRow['player_tid'] : 0;
            /** @phpstan-ignore argument.type (DB row guarantees array shape) */
            $transformedPlayer = $boxscoreTransformer->transformPlayerLine($playerRow);
            if ($playerTid === $visitorTeamId) {
                $visitorPlayers[] = $transformedPlayer;
            } else {
                $homePlayers[] = $transformedPlayer;
            }
        }

        $data = [
            'game' => $gameData,
            'visitor' => [
                'team_stats' => $visitorTeamStats,
                'players' => $visitorPlayers,
            ],
            'home' => [
                'team_stats' => $homeTeamStats,
                'players' => $homePlayers,
            ],
        ];

        $responder->success($data, [], 200, $etag->getHeaders($tag));
    }
}
