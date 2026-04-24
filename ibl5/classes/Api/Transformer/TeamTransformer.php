<?php

declare(strict_types=1);

namespace Api\Transformer;

/**
 * @phpstan-import-type TeamListRow from \Api\Repository\ApiTeamRepository
 * @phpstan-import-type TeamDetailRow from \Api\Repository\ApiTeamRepository
 */
class TeamTransformer
{
    /**
     * Transform a team row for list endpoints.
     *
     * @param TeamListRow $row
     * @return array<string, mixed>
     */
    public function transform(array $row): array
    {
        return [
            'uuid' => $row['uuid'],
            'city' => $row['team_city'],
            'name' => $row['team_name'],
            'full_name' => $row['team_city'] . ' ' . $row['team_name'],
            'team_id' => $row['teamid'],
            'owner' => $row['owner_name'],
            'owner_discord_id' => $row['discord_id'],
            'arena' => $row['arena'],
            'conference' => $row['conference'],
            'division' => $row['division'],
        ];
    }

    /**
     * Transform a team row for detail endpoint (includes standings/power data).
     *
     * @param TeamDetailRow $row
     * @return array<string, mixed>
     */
    public function transformDetail(array $row): array
    {
        $base = $this->transform($row);

        $base['record'] = [
            'league' => $row['league_record'],
            'conference' => $row['conference_record'],
            'division' => $row['division_record'],
            'home' => $row['home_wins'] !== null && $row['home_losses'] !== null
                ? $row['home_wins'] . '-' . $row['home_losses']
                : null,
            'away' => $row['away_wins'] !== null && $row['away_losses'] !== null
                ? $row['away_wins'] . '-' . $row['away_losses']
                : null,
        ];

        $base['standings'] = [
            'win_percentage' => $row['win_percentage'],
            'conference_games_back' => $row['conference_games_back'],
            'division_games_back' => $row['division_games_back'],
            'games_remaining' => $row['games_remaining'],
        ];

        return $base;
    }
}
