<?php

declare(strict_types=1);

namespace SimRecap;

use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * Builds the team name → Discord snowflake map that scripts/simRecapQueue.php
 * emits for the `mention-map` verb (consumed by bin/sim-recap-tick via jq).
 *
 * Teams with no id are omitted entirely so the prompt's plain-team-name
 * fallback fires on a missing key and no "<@null>" can ever be built.
 *
 * The snowflake is returned as a STRING, never an int: json_encode would emit
 * an int as a bare JSON number, which round-trips only under jq >= 1.7 with no
 * arithmetic and corrupts under jq <= 1.6 (IEEE-754) or any other consumer.
 *
 * State-free: the only collaborator is the injected repository contract. No
 * DB handle, session, or actor identity reaches this class.
 */
final class MentionMapBuilder
{
    public function __construct(
        private readonly TeamIdentityRepositoryInterface $teams,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function build(): array
    {
        $map = [];
        foreach ($this->teams->getAllRealTeams() as $team) {
            $name = $team['team_name'];
            $id = $this->teams->getTeamDiscordID($name);
            if ($id === null) {
                continue;
            }
            $map[$name] = (string) $id;
        }

        return $map;
    }
}
