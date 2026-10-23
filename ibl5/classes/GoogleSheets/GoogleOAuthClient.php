<?php

declare(strict_types=1);

namespace GoogleSheets;

use GoogleSheets\Contracts\GoogleHttpClientInterface;

/**
 * Google OAuth 2.0 web-server flow over the HTTP seam.
 *
 * Tokens travel only in POST bodies; none is placed in a URL, header value, or exception.
 * The class takes no logger: callers log status and reason from the typed exception.
 */
final class GoogleOAuthClient
{
    public const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    public const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';
    public const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    private const FORM_HEADER = 'Content-Type: application/x-www-form-urlencoded';

    public function __construct(
        private readonly GoogleOAuthConfig $config,
        private readonly GoogleHttpClientInterface $http,
    ) {
    }

    public function buildAuthorizationUrl(string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => $this->config->clientId,
            'redirect_uri' => $this->config->redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /**
     * @throws GoogleApiException
     * @throws GoogleHttpException
     */
    public function exchangeCode(string $code): GoogleTokens
    {
        $response = $this->postForm(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
            'redirect_uri' => $this->config->redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if ($response['status'] !== 200) {
            throw GoogleApiException::fromResponse($response['status'], $response['body']);
        }

        $data = GoogleJson::decode($response['body']);
        $accessToken = is_array($data) ? ($data['access_token'] ?? null) : null;
        $refreshToken = is_array($data) ? ($data['refresh_token'] ?? null) : null;

        if (!is_string($accessToken) || $accessToken === '') {
            throw new GoogleApiException('Google token response missing access_token', 200, 'no_access_token');
        }
        if (!is_string($refreshToken) || $refreshToken === '') {
            throw new GoogleApiException('Google token response missing refresh_token', 200, 'no_refresh_token');
        }

        return new GoogleTokens($accessToken, $refreshToken);
    }

    /**
     * @throws GoogleGrantRevokedException When Google reports invalid_grant
     * @throws GoogleApiException
     * @throws GoogleHttpException
     */
    public function refreshAccessToken(#[\SensitiveParameter] string $refreshToken): string
    {
        $response = $this->postForm(self::TOKEN_URL, [
            'refresh_token' => $refreshToken,
            'client_id' => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
            'grant_type' => 'refresh_token',
        ]);

        if ($response['status'] !== 200) {
            $exception = GoogleApiException::fromResponse($response['status'], $response['body']);
            if (
                ($response['status'] === 400 || $response['status'] === 401)
                && $exception->reason === 'invalid_grant'
            ) {
                throw new GoogleGrantRevokedException($exception->getMessage(), $exception->status, $exception->reason);
            }
            throw $exception;
        }

        $data = GoogleJson::decode($response['body']);
        $accessToken = is_array($data) ? ($data['access_token'] ?? null) : null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new GoogleApiException('Google token response missing access_token', 200, 'no_access_token');
        }

        return $accessToken;
    }

    /**
     * Best-effort revocation. Never throws: disconnect must finish locally.
     */
    public function revoke(#[\SensitiveParameter] string $token): bool
    {
        try {
            $response = $this->postForm(self::REVOKE_URL, ['token' => $token]);
        } catch (GoogleHttpException) {
            return false;
        }

        return $response['status'] === 200;
    }

    /**
     * @param array<string, string> $fields
     * @return array{status: int, body: string}
     */
    private function postForm(string $url, array $fields): array
    {
        return $this->http->request('POST', $url, [self::FORM_HEADER], http_build_query($fields));
    }
}
