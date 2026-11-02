<?php

declare(strict_types=1);

namespace GoogleSheets;

final readonly class GoogleTokens
{
    public function __construct(
        #[\SensitiveParameter] public string $accessToken,
        #[\SensitiveParameter] public string $refreshToken,
    ) {
    }
}
