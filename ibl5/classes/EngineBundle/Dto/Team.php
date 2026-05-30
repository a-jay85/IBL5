<?php

declare(strict_types=1);

namespace EngineBundle\Dto;

/**
 * One team in the engine input bundle. Serializes to {@code {"teamid": int, "name": string}}.
 */
final class Team
{
    public function __construct(
        public readonly int $teamid,
        public readonly string $name,
    ) {
    }
}
