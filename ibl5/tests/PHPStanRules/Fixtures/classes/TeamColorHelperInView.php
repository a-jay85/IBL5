<?php

declare(strict_types=1);

class TeamColorHelperInView
{
    public function render(int $teamId): string
    {
        $colors = \Player\Views\TeamColorHelper::getTeamColors(new \mysqli(), $teamId);

        return '';
    }
}
