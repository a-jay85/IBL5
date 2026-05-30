<?php

declare(strict_types=1);

namespace EngineBundle;

use EngineBundle\Contracts\BundleSerializerInterface;
use EngineBundle\Dto\Bundle;

/**
 * Serializes a {@see Bundle} to the JSON the Go engine decodes
 * (engine/internal/bundle/bundle.go).
 *
 * This class is the single source of the JSON key spelling. Player fields are
 * already keyed by the contract names (see {@see \EngineBundle\Dto\Player::FIELDS}),
 * so they pass through; teams, games, and the envelope are mapped explicitly
 * here — notably the schedule's `home_teamid`/`visitor_teamid`/`game_date` →
 * `home_team_id`/`visitor_team_id`/`date`.
 *
 * Goal is decode-compatibility, not byte-identity: JSON object key order is
 * irrelevant to Go's json.Unmarshal.
 */
final class BundleSerializer implements BundleSerializerInterface
{
    /**
     * @see BundleSerializerInterface::serialize()
     */
    public function serialize(Bundle $bundle): string
    {
        $teams = [];
        foreach ($bundle->teams as $team) {
            $teams[] = [
                'teamid' => $team->teamid,
                'name' => $team->name,
            ];
        }

        $players = [];
        foreach ($bundle->players as $player) {
            // Keys already equal the Go contract tags (== ibl_plr columns).
            $players[] = $player->fields;
        }

        $schedule = [];
        foreach ($bundle->schedule as $game) {
            $schedule[] = [
                'home_team_id' => $game->homeTeamId,
                'visitor_team_id' => $game->visitorTeamId,
                'date' => $game->date,
                'game_type' => $game->gameType,
            ];
        }

        $payload = [
            'league_id' => $bundle->leagueId,
            'seed' => $bundle->seed,
            'teams' => $teams,
            'players' => $players,
            'schedule' => $schedule,
        ];

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
