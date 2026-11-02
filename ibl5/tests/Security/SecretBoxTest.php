<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use Security\SecretBox;
use Security\SecretBoxDecryptException;
use Security\SecretBoxKeyUnavailableException;

class SecretBoxTest extends TestCase
{
    private const PLAINTEXT = 'refresh-token-plain';

    private string|false $savedKey;
    private string|false $savedPrevious;

    protected function setUp(): void
    {
        $this->savedKey = getenv(SecretBox::ENV_KEY);
        $this->savedPrevious = getenv(SecretBox::ENV_KEY_PREVIOUS);
    }

    protected function tearDown(): void
    {
        putenv($this->savedKey === false ? SecretBox::ENV_KEY : SecretBox::ENV_KEY . '=' . $this->savedKey);
        putenv($this->savedPrevious === false ? SecretBox::ENV_KEY_PREVIOUS : SecretBox::ENV_KEY_PREVIOUS . '=' . $this->savedPrevious);
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $box = new SecretBox(random_bytes(32));

        self::assertSame('tok', $box->decrypt($box->encrypt('tok')));
    }

    public function testCiphertextIsVersionedRandomizedAndNeverContainsPlaintext(): void
    {
        $box = new SecretBox(random_bytes(32));

        $first = $box->encrypt(self::PLAINTEXT);
        $second = $box->encrypt(self::PLAINTEXT);

        self::assertStringStartsWith('v1.', $first);
        self::assertNotSame($first, $second);
        self::assertStringNotContainsString(self::PLAINTEXT, $first);
        self::assertStringNotContainsString(base64_encode(self::PLAINTEXT), $first);
    }

    public function testTamperedCiphertextThrowsDecryptException(): void
    {
        $box = new SecretBox(random_bytes(32));
        $raw = base64_decode(substr($box->encrypt(self::PLAINTEXT), 3), true);
        self::assertIsString($raw);
        $last = strlen($raw) - 1;
        $raw[$last] = chr(ord($raw[$last]) ^ 0x01);

        $this->expectException(SecretBoxDecryptException::class);
        $box->decrypt('v1.' . base64_encode($raw));
    }

    public function testWrongKeyThrowsDecryptException(): void
    {
        $ciphertext = (new SecretBox(random_bytes(32)))->encrypt(self::PLAINTEXT);

        $this->expectException(SecretBoxDecryptException::class);
        (new SecretBox(random_bytes(32)))->decrypt($ciphertext);
    }

    public function testPreviousKeyDecryptsAndNeedsReencryptIsTrue(): void
    {
        $keyA = random_bytes(32);
        $keyB = random_bytes(32);
        $ciphertext = (new SecretBox($keyA))->encrypt(self::PLAINTEXT);

        $rotated = new SecretBox($keyB, $keyA);
        self::assertSame(self::PLAINTEXT, $rotated->decrypt($ciphertext));
        self::assertTrue($rotated->needsReencrypt($ciphertext));
        self::assertFalse($rotated->needsReencrypt($rotated->encrypt(self::PLAINTEXT)));
        self::assertFalse((new SecretBox($keyA))->needsReencrypt($ciphertext));
    }

    public function testUnsupportedPrefixThrows(): void
    {
        $box = new SecretBox(random_bytes(32));

        foreach (['v0.abc', 'plain', 'v1.!!!not-base64', 'v1.' . base64_encode('short')] as $bad) {
            try {
                $box->decrypt($bad);
                self::fail('Expected SecretBoxDecryptException for ' . $bad);
            } catch (SecretBoxDecryptException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testFromEnvThrowsWhenKeyUnset(): void
    {
        putenv(SecretBox::ENV_KEY);

        $this->expectException(SecretBoxKeyUnavailableException::class);
        SecretBox::fromEnv();
    }

    public function testFromEnvThrowsWhenKeyEmpty(): void
    {
        putenv(SecretBox::ENV_KEY . '=');

        $this->expectException(SecretBoxKeyUnavailableException::class);
        SecretBox::fromEnv();
    }

    public function testFromEnvThrowsWhenKeyWrongLength(): void
    {
        putenv(SecretBox::ENV_KEY . '=' . base64_encode(random_bytes(16)));

        $this->expectException(SecretBoxKeyUnavailableException::class);
        SecretBox::fromEnv();
    }

    public function testFromEnvAcceptsValidKeyAndTreatsEmptyPreviousAsAbsent(): void
    {
        putenv(SecretBox::ENV_KEY . '=' . base64_encode(random_bytes(32)));
        putenv(SecretBox::ENV_KEY_PREVIOUS . '=');

        $box = SecretBox::fromEnv();

        self::assertFalse($box->needsReencrypt($box->encrypt('x')));
        self::assertSame('x', $box->decrypt($box->encrypt('x')));
    }

    public function testFromEnvThrowsWhenPreviousKeyMalformed(): void
    {
        putenv(SecretBox::ENV_KEY . '=' . base64_encode(random_bytes(32)));
        putenv(SecretBox::ENV_KEY_PREVIOUS . '=not-base64!');

        $this->expectException(SecretBoxKeyUnavailableException::class);
        SecretBox::fromEnv();
    }

    public function testExceptionMessagesNeverContainKeyOrPlaintext(): void
    {
        $validKey = base64_encode(random_bytes(32));
        $shortKey = base64_encode(random_bytes(16));
        $messages = [];

        $envCases = [
            [$shortKey, null],
            ['not-base64!', null],
            [$validKey, $shortKey],
            [$validKey, 'not-base64!'],
        ];
        foreach ($envCases as [$current, $previous]) {
            putenv(SecretBox::ENV_KEY . '=' . $current);
            putenv($previous === null ? SecretBox::ENV_KEY_PREVIOUS : SecretBox::ENV_KEY_PREVIOUS . '=' . $previous);
            try {
                SecretBox::fromEnv();
                self::fail('Expected SecretBoxKeyUnavailableException');
            } catch (SecretBoxKeyUnavailableException $e) {
                $messages[] = $e->getMessage();
            }
        }

        $ciphertext = (new SecretBox(random_bytes(32)))->encrypt(self::PLAINTEXT);
        try {
            (new SecretBox(random_bytes(32)))->decrypt($ciphertext);
            self::fail('Expected SecretBoxDecryptException');
        } catch (SecretBoxDecryptException $e) {
            $messages[] = $e->getMessage();
        }

        self::assertCount(5, $messages);
        foreach ($messages as $message) {
            self::assertStringNotContainsString($validKey, $message);
            self::assertStringNotContainsString($shortKey, $message);
            self::assertStringNotContainsString('not-base64!', $message);
            self::assertStringNotContainsString(self::PLAINTEXT, $message);
            self::assertStringNotContainsString(substr($ciphertext, 3, 20), $message);
        }
    }
}
