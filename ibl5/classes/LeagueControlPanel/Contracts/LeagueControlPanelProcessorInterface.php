<?php

declare(strict_types=1);

namespace LeagueControlPanel\Contracts;

interface LeagueControlPanelProcessorInterface
{
    /**
     * Dispatch a POST action to the appropriate handler
     *
     * @param string $action The action key (e.g., 'set_season_phase', 'reset_asg_voting')
     * @param array<string, mixed> $postData The full POST data
     * @return array{success: bool, message: string}
     */
    public function dispatch(string $action, array $postData): array;
}
