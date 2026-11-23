<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\classes\Module\Factories;

final class IdentityParamFactory
{
    public function controller(int $loggedInTeamId): int
    {
        return $loggedInTeamId;
    }

    public function withSession(string $sessionToken): string
    {
        return $sessionToken;
    }

    public function googleConfig(string $host, bool $https): string
    {
        return $https ? $host : '';
    }
}
