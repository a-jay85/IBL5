<?php

declare(strict_types=1);

namespace GoogleSheets;

final readonly class GoogleOAuthConfig
{
    private const ENV_CLIENT_ID = 'GOOGLE_OAUTH_CLIENT_ID';
    private const ENV_CLIENT_SECRET = 'GOOGLE_OAUTH_CLIENT_SECRET';
    private const ENV_REDIRECT_URI = 'GOOGLE_OAUTH_REDIRECT_URI';

    public function __construct(
        public string $clientId,
        #[\SensitiveParameter] public string $clientSecret,
        public string $redirectUri,
    ) {
    }

    /**
     * @throws GoogleOAuthNotConfiguredException When a required variable is unset or empty
     */
    public static function fromEnv(string $requestHost, bool $isHttps): self
    {
        $clientId = self::readEnv(self::ENV_CLIENT_ID);
        if ($clientId === null) {
            throw new GoogleOAuthNotConfiguredException(self::ENV_CLIENT_ID . ' is not configured');
        }

        $clientSecret = self::readEnv(self::ENV_CLIENT_SECRET);
        if ($clientSecret === null) {
            throw new GoogleOAuthNotConfiguredException(self::ENV_CLIENT_SECRET . ' is not configured');
        }

        $redirectUri = self::readEnv(self::ENV_REDIRECT_URI)
            ?? ($isHttps ? 'https' : 'http') . '://' . $requestHost . '/ibl5/modules.php?name=ApiKeys&op=google_callback';

        return new self($clientId, $clientSecret, $redirectUri);
    }

    public static function isConfigured(): bool
    {
        return self::readEnv(self::ENV_CLIENT_ID) !== null
            && self::readEnv(self::ENV_CLIENT_SECRET) !== null;
    }

    private static function readEnv(string $name): ?string
    {
        $value = getenv($name);
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
