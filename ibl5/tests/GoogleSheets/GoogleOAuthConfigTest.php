<?php

declare(strict_types=1);

namespace Tests\GoogleSheets;

use GoogleSheets\GoogleOAuthConfig;
use GoogleSheets\GoogleOAuthNotConfiguredException;
use PHPUnit\Framework\TestCase;

class GoogleOAuthConfigTest extends TestCase
{
    private const VARS = ['GOOGLE_OAUTH_CLIENT_ID', 'GOOGLE_OAUTH_CLIENT_SECRET', 'GOOGLE_OAUTH_REDIRECT_URI'];

    /** @var array<string, string|false> */
    private array $snapshot = [];

    protected function setUp(): void
    {
        foreach (self::VARS as $name) {
            $this->snapshot[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->snapshot as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
    }

    public function testConfigThrowsWhenClientIdUnset(): void
    {
        putenv('GOOGLE_OAUTH_CLIENT_SECRET=s3cret');

        try {
            GoogleOAuthConfig::fromEnv('example.test', true);
            self::fail('expected GoogleOAuthNotConfiguredException');
        } catch (GoogleOAuthNotConfiguredException $e) {
            self::assertStringContainsString('GOOGLE_OAUTH_CLIENT_ID', $e->getMessage());
        }
    }

    public function testConfigThrowsWhenClientIdEmpty(): void
    {
        putenv('GOOGLE_OAUTH_CLIENT_ID=');
        putenv('GOOGLE_OAUTH_CLIENT_SECRET=s3cret');

        try {
            GoogleOAuthConfig::fromEnv('example.test', true);
            self::fail('expected GoogleOAuthNotConfiguredException');
        } catch (GoogleOAuthNotConfiguredException $e) {
            self::assertStringContainsString('GOOGLE_OAUTH_CLIENT_ID', $e->getMessage());
        }
    }

    public function testConfigThrowsWhenSecretEmpty(): void
    {
        putenv('GOOGLE_OAUTH_CLIENT_ID=cid');
        putenv('GOOGLE_OAUTH_CLIENT_SECRET=');

        try {
            GoogleOAuthConfig::fromEnv('example.test', true);
            self::fail('Expected GoogleOAuthNotConfiguredException');
        } catch (GoogleOAuthNotConfiguredException $e) {
            self::assertStringContainsString('GOOGLE_OAUTH_CLIENT_SECRET', $e->getMessage());
            self::assertStringNotContainsString('cid', $e->getMessage());
        }
    }

    public function testConfigDefaultsRedirectUriFromRequestHost(): void
    {
        putenv('GOOGLE_OAUTH_CLIENT_ID=cid');
        putenv('GOOGLE_OAUTH_CLIENT_SECRET=s3cret');
        putenv('GOOGLE_OAUTH_REDIRECT_URI=');

        $http = GoogleOAuthConfig::fromEnv('my-slug.localhost', false);
        $https = GoogleOAuthConfig::fromEnv('iblhoops.net', true);

        self::assertSame('cid', $http->clientId);
        self::assertSame('s3cret', $http->clientSecret);
        self::assertSame('http://my-slug.localhost/ibl5/modules.php?name=ApiKeys&op=google_callback', $http->redirectUri);
        self::assertSame('https://iblhoops.net/ibl5/modules.php?name=ApiKeys&op=google_callback', $https->redirectUri);
    }

    public function testConfigUsesExplicitRedirectUriWhenSet(): void
    {
        putenv('GOOGLE_OAUTH_CLIENT_ID=cid');
        putenv('GOOGLE_OAUTH_CLIENT_SECRET=s3cret');
        putenv('GOOGLE_OAUTH_REDIRECT_URI=https://registered.example/cb');

        $config = GoogleOAuthConfig::fromEnv('ignored.localhost', false);

        self::assertSame('https://registered.example/cb', $config->redirectUri);
    }

    public function testIsConfiguredFalseWhenEitherVarMissing(): void
    {
        self::assertFalse(GoogleOAuthConfig::isConfigured());

        putenv('GOOGLE_OAUTH_CLIENT_ID=cid');
        self::assertFalse(GoogleOAuthConfig::isConfigured());

        putenv('GOOGLE_OAUTH_CLIENT_ID');
        putenv('GOOGLE_OAUTH_CLIENT_SECRET=s3cret');
        self::assertFalse(GoogleOAuthConfig::isConfigured());

        putenv('GOOGLE_OAUTH_CLIENT_SECRET=');
        putenv('GOOGLE_OAUTH_CLIENT_ID=cid');
        self::assertFalse(GoogleOAuthConfig::isConfigured());

        putenv('GOOGLE_OAUTH_CLIENT_SECRET=s3cret');
        self::assertTrue(GoogleOAuthConfig::isConfigured());
    }
}
