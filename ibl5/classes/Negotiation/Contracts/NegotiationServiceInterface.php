<?php

declare(strict_types=1);

namespace Negotiation\Contracts;

interface NegotiationServiceInterface
{
    public function processNegotiation(int $playerID, string $userTeamName, string $prefix, bool $bypassOwnership = false): string;
}
