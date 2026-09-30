<?php

declare(strict_types=1);

namespace Tests\GoogleSheets;

use GoogleSheets\GoogleApiException;
use GoogleSheets\GoogleGrantRevokedException;
use GoogleSheets\GoogleOAuthClient;
use GoogleSheets\GoogleOAuthConfig;
use PHPUnit\Framework\TestCase;
use Tests\GoogleSheets\Fakes\FakeGoogleHttpClient;

class GoogleOAuthClientTest extends TestCase
{
    private const TOKEN_OK = '{"access_token":"ya29.x","expires_in":3599,"refresh_token":"1//rt","scope":"https://www.googleapis.com/auth/drive.file","token_type":"Bearer"}';
    private const REFRESH_OK = '{"access_token":"ya29.fresh","expires_in":3599,"scope":"https://www.googleapis.com/auth/drive.file","token_type":"Bearer"}';
    private const INVALID_GRANT = '{"error":"invalid_grant","error_description":"Token has been expired or revoked."}';

    private FakeGoogleHttpClient $http;
    private GoogleOAuthClient $client;

    protected function setUp(): void
    {
        $this->http = new FakeGoogleHttpClient();
        $this->client = new GoogleOAuthClient(
            new GoogleOAuthConfig('cid.apps.googleusercontent.com', 'GOCSPX-secret', 'http://x.localhost/ibl5/cb'),
            $this->http,
        );
    }

    public function testAuthorizationUrlCarriesStateOfflineAccessAndConsentPrompt(): void
    {
        $url = $this->client->buildAuthorizationUrl('state-abc');

        self::assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('state-abc', $query['state']);
        self::assertSame('offline', $query['access_type']);
        self::assertSame('consent', $query['prompt']);
        self::assertSame('code', $query['response_type']);
        self::assertSame('cid.apps.googleusercontent.com', $query['client_id']);
        self::assertSame('http://x.localhost/ibl5/cb', $query['redirect_uri']);
    }

    public function testAuthorizationUrlRequestsOnlyDriveFileScope(): void
    {
        parse_str((string) parse_url($this->client->buildAuthorizationUrl('s'), PHP_URL_QUERY), $query);

        self::assertSame('https://www.googleapis.com/auth/drive.file', $query['scope']);
    }

    public function testExchangeCodePostsFormBodyAndReturnsBothTokens(): void
    {
        $this->http->queue(200, self::TOKEN_OK);

        $tokens = $this->client->exchangeCode('4/0Acode');

        self::assertSame('ya29.x', $tokens->accessToken);
        self::assertSame('1//rt', $tokens->refreshToken);
        self::assertCount(1, $this->http->requests);
        $request = $this->http->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame('https://oauth2.googleapis.com/token', $request['url']);
        self::assertContains('Content-Type: application/x-www-form-urlencoded', $request['headers']);
        parse_str((string) $request['body'], $form);
        self::assertSame([
            'code' => '4/0Acode',
            'client_id' => 'cid.apps.googleusercontent.com',
            'client_secret' => 'GOCSPX-secret',
            'redirect_uri' => 'http://x.localhost/ibl5/cb',
            'grant_type' => 'authorization_code',
        ], $form);
    }

    public function testExchangeCodeWithoutRefreshTokenThrowsApiException(): void
    {
        $this->http->queue(200, self::REFRESH_OK);

        try {
            $this->client->exchangeCode('4/0Acode');
            self::fail('Expected GoogleApiException');
        } catch (GoogleApiException $e) {
            self::assertSame('no_refresh_token', $e->reason);
        }
    }

    public function testRefreshAccessTokenReturnsAccessToken(): void
    {
        $this->http->queue(200, self::REFRESH_OK);

        $accessToken = $this->client->refreshAccessToken('1//rt');

        self::assertSame('ya29.fresh', $accessToken);
        $request = $this->http->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame('https://oauth2.googleapis.com/token', $request['url']);
        parse_str((string) $request['body'], $form);
        self::assertSame('refresh_token', $form['grant_type']);
        self::assertSame('1//rt', $form['refresh_token']);
    }

    public function testRefreshInvalidGrantThrowsGrantRevokedException(): void
    {
        $this->http->queue(400, self::INVALID_GRANT);
        $this->http->queue(401, self::INVALID_GRANT);

        foreach ([400, 401] as $status) {
            try {
                $this->client->refreshAccessToken('1//rt');
                self::fail('Expected GoogleGrantRevokedException');
            } catch (GoogleGrantRevokedException $e) {
                self::assertSame($status, $e->status);
                self::assertSame('invalid_grant', $e->reason);
            }
        }
    }

    public function testRefreshOtherErrorThrowsApiExceptionWithStatusAndReason(): void
    {
        $this->http->queue(400, '{"error":"invalid_client","error_description":"Unauthorized"}');
        $this->http->queue(503, '{"error":{"code":503,"message":"The service is unavailable.","status":"UNAVAILABLE"}}');

        try {
            $this->client->refreshAccessToken('1//rt');
            self::fail('Expected GoogleApiException');
        } catch (GoogleApiException $e) {
            self::assertNotInstanceOf(GoogleGrantRevokedException::class, $e);
            self::assertSame(400, $e->status);
            self::assertSame('invalid_client', $e->reason);
        }

        try {
            $this->client->refreshAccessToken('1//rt');
            self::fail('Expected GoogleApiException');
        } catch (GoogleApiException $e) {
            self::assertNotInstanceOf(GoogleGrantRevokedException::class, $e);
            self::assertSame(503, $e->status);
            self::assertSame('UNAVAILABLE', $e->reason);
        }
    }

    public function testApiExceptionMessageNeverEchoesRefreshToken(): void
    {
        $refreshToken = '1//super-secret-refresh-token';
        $this->http->queue(400, '{"error":"invalid_request","error_description":"Bad request"}');

        try {
            $this->client->refreshAccessToken($refreshToken);
            self::fail('Expected GoogleApiException');
        } catch (GoogleApiException $e) {
            self::assertStringNotContainsString($refreshToken, $e->getMessage());
            self::assertStringNotContainsString('GOCSPX-secret', $e->getMessage());
            self::assertStringNotContainsString('oauth2.googleapis.com', $e->getMessage());
        }

        $long = GoogleApiException::fromResponse(500, (string) json_encode([
            'error' => 'x',
            'error_description' => str_repeat('a', 500) . $refreshToken,
        ]));
        self::assertStringNotContainsString($refreshToken, $long->getMessage());
        self::assertLessThan(260, strlen($long->getMessage()));

        $garbage = GoogleApiException::fromResponse(502, '<html>' . $refreshToken . '</html>');
        self::assertSame('unknown', $garbage->reason);
        self::assertStringNotContainsString($refreshToken, $garbage->getMessage());
    }

    public function testRevokeReturnsFalseOnNon200AndOnTransportErrorWithoutThrowing(): void
    {
        $this->http->queue(200, '{}');
        $this->http->queue(400, '{"error":"invalid_token"}');
        $this->http->queueTransportError('Could not resolve host');

        self::assertTrue($this->client->revoke('1//rt'));
        self::assertFalse($this->client->revoke('1//rt'));
        self::assertFalse($this->client->revoke('1//rt'));

        $request = $this->http->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame('https://oauth2.googleapis.com/revoke', $request['url']);
        parse_str((string) $request['body'], $form);
        self::assertSame(['token' => '1//rt'], $form);
    }

    public function testTokensNeverAppearInRequestUrlsOrHeaders(): void
    {
        $this->http->queue(200, self::TOKEN_OK);
        $this->http->queue(200, self::REFRESH_OK);
        $this->http->queue(200, '{}');

        $this->client->exchangeCode('4/0Acode');
        $this->client->refreshAccessToken('1//rt');
        $this->client->revoke('1//rt');

        self::assertCount(3, $this->http->requests);
        foreach ($this->http->requests as $request) {
            foreach (['1//rt', rawurlencode('1//rt'), 'ya29', '4/0Acode', 'GOCSPX-secret'] as $secret) {
                self::assertStringNotContainsString($secret, $request['url']);
                self::assertStringNotContainsString($secret, implode("\n", $request['headers']));
            }
            self::assertStringNotContainsString('?', $request['url']);
        }
    }
}
