<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures;

final class RequestReadOutsideFactory
{
    public function __construct(private readonly \Http\HttpRequest $request)
    {
    }

    public function host(int $loggedInTeamId): mixed
    {
        return $loggedInTeamId > 0 ? $this->request->server('HTTP_HOST') : null;
    }
}
