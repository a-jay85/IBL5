<?php

declare(strict_types=1);

namespace Api\Transformer;

/**
 * @phpstan-import-type InjuredPlayerRow from \Api\Repository\ApiInjuriesRepository
 */
class InjuryTransformer
{
    /**
     * Transform an injured player row.
     *
     * @param InjuredPlayerRow $row
     * @return array<string, mixed>
     */
    public function transform(array $row): array
    {
        return [
            'player' => [
                'uuid' => $row['player_uuid'],
                'pid' => $row['pid'],
                'name' => $row['name'],
                'position' => $row['pos'],
            ],
            'team' => [
                'uuid' => $row['team_uuid'],
                'city' => $row['team_city'] ?? '',
                'name' => $row['team_name'] ?? '',
                'team_id' => $row['teamid'] ?? 0,
            ],
            'injury' => [
                'days_remaining' => $row['injured'],
            ],
        ];
    }
}
