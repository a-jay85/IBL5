<?php

declare(strict_types=1);

namespace Security;

/**
 * SecretBox - authenticated symmetric encryption for secrets stored at rest.
 *
 * The only code path allowed to turn a secret (a Google refresh token) into a
 * stored string and back. Uses libsodium secretbox (XSalsa20-Poly1305) with a
 * random nonce per encryption. Ciphertext format: `v1.` + base64(nonce . box).
 *
 * Key rotation: GOOGLE_TOKEN_KEY_PREVIOUS holds the old key while rows are
 * lazily re-encrypted under GOOGLE_TOKEN_KEY (see needsReencrypt()).
 * Every failure path throws; nothing falls back to a default key.
 */
final class SecretBox
{
    public const ENV_KEY = 'GOOGLE_TOKEN_KEY';
    public const ENV_KEY_PREVIOUS = 'GOOGLE_TOKEN_KEY_PREVIOUS';

    private const VERSION = 'v1';

    private readonly string $currentKey;
    private readonly ?string $previousKey;

    /**
     * @param string $currentKey Raw 32-byte key
     * @param string|null $previousKey Raw 32-byte key used before rotation, if any
     */
    public function __construct(#[\SensitiveParameter] string $currentKey, #[\SensitiveParameter] ?string $previousKey = null)
    {
        self::assertSodiumLoaded();
        if (strlen($currentKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new SecretBoxKeyUnavailableException(self::ENV_KEY . ' must be base64 of 32 bytes');
        }
        if ($previousKey !== null && strlen($previousKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new SecretBoxKeyUnavailableException(self::ENV_KEY_PREVIOUS . ' must be base64 of 32 bytes');
        }

        $this->currentKey = $currentKey;
        $this->previousKey = $previousKey;
    }

    /**
     * Build from GOOGLE_TOKEN_KEY (required) and GOOGLE_TOKEN_KEY_PREVIOUS (optional).
     *
     * @throws SecretBoxKeyUnavailableException When the key is unset, empty, or malformed
     */
    public static function fromEnv(): self
    {
        self::assertSodiumLoaded();

        $current = getenv(self::ENV_KEY);
        if ($current === false || $current === '') {
            throw new SecretBoxKeyUnavailableException(self::ENV_KEY . ' is not set');
        }
        $currentKey = self::decodeKey($current, self::ENV_KEY);

        $previous = getenv(self::ENV_KEY_PREVIOUS);
        $previousKey = ($previous === false || $previous === '')
            ? null
            : self::decodeKey($previous, self::ENV_KEY_PREVIOUS);

        return new self($currentKey, $previousKey);
    }

    public function encrypt(#[\SensitiveParameter] string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $box = sodium_crypto_secretbox($plaintext, $nonce, $this->currentKey);

        return self::VERSION . '.' . sodium_bin2base64($nonce . $box, SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    /**
     * @throws SecretBoxDecryptException When the format is unknown or no key authenticates
     */
    public function decrypt(string $ciphertext): string
    {
        [$nonce, $box] = $this->split($ciphertext);

        $plaintext = sodium_crypto_secretbox_open($box, $nonce, $this->currentKey);
        if ($plaintext === false && $this->previousKey !== null) {
            $plaintext = sodium_crypto_secretbox_open($box, $nonce, $this->previousKey);
        }
        if ($plaintext === false) {
            throw new SecretBoxDecryptException('ciphertext did not authenticate');
        }

        return $plaintext;
    }

    /**
     * True only when the current key fails and the previous key authenticates.
     */
    public function needsReencrypt(string $ciphertext): bool
    {
        if ($this->previousKey === null) {
            return false;
        }
        try {
            [$nonce, $box] = $this->split($ciphertext);
        } catch (SecretBoxDecryptException) {
            return false;
        }

        return sodium_crypto_secretbox_open($box, $nonce, $this->currentKey) === false
            && sodium_crypto_secretbox_open($box, $nonce, $this->previousKey) !== false;
    }

    /**
     * @return array{0: non-empty-string, 1: string}
     */
    private function split(string $ciphertext): array
    {
        $prefix = self::VERSION . '.';
        if (!str_starts_with($ciphertext, $prefix)) {
            throw new SecretBoxDecryptException('unsupported ciphertext format');
        }

        try {
            $raw = sodium_base642bin(substr($ciphertext, strlen($prefix)), SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (\SodiumException) {
            throw new SecretBoxDecryptException('unsupported ciphertext format');
        }

        if (strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new SecretBoxDecryptException('unsupported ciphertext format');
        }

        /** @var non-empty-string $nonce */
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return [$nonce, substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)];
    }

    private static function decodeKey(#[\SensitiveParameter] string $encoded, string $name): string
    {
        $decoded = base64_decode($encoded, true);
        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new SecretBoxKeyUnavailableException($name . ' must be base64 of 32 bytes');
        }

        return $decoded;
    }

    private static function assertSodiumLoaded(): void
    {
        if (!extension_loaded('sodium')) {
            throw new SecretBoxKeyUnavailableException('ext-sodium is required');
        }
    }
}
