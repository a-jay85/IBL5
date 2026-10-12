<?php

declare(strict_types=1);

namespace Tests\EventLog;

use EventLog\SessionIdHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SessionIdHasherTest extends TestCase
{
    private const RAW_SESSION_ID = 'sessRAW-k3j5h2g8f9d0s1a2p4o6i7u8';

    /** @return array<string, array{string}> */
    public static function nonEmptyInputProvider(): array
    {
        return [
            'typical PHP session id'  => ['k3j5h2g8f9d0s1a2p4o6i7u8y9t0r1e2'],
            'non-hex raw id'          => [self::RAW_SESSION_ID],
            'single character'        => ['a'],
            'long 256-char id'        => [str_repeat('z', 256)],
            'string zero is non-empty' => ['0'],
        ];
    }

    #[DataProvider('nonEmptyInputProvider')]
    public function testHashReturnsSixtyFourLowercaseHexChars(string $raw): void
    {
        $hashed = SessionIdHasher::hash($raw);

        self::assertNotNull($hashed);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $hashed);
    }

    #[DataProvider('nonEmptyInputProvider')]
    public function testHashIsByteIdenticalToInlineSha256(string $raw): void
    {
        self::assertSame(hash('sha256', $raw), SessionIdHasher::hash($raw));
    }

    public function testHashMatchesPublishedSha256VectorForAbc(): void
    {
        self::assertSame(
            'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad',
            SessionIdHasher::hash('abc'),
        );
    }

    public function testHashOutputNeverContainsRawInput(): void
    {
        $hashed = SessionIdHasher::hash(self::RAW_SESSION_ID);

        self::assertNotNull($hashed);
        self::assertStringNotContainsString(self::RAW_SESSION_ID, $hashed);
        self::assertStringNotContainsString('sessRAW', $hashed);
    }

    public function testHashIsDeterministic(): void
    {
        self::assertSame(
            SessionIdHasher::hash(self::RAW_SESSION_ID),
            SessionIdHasher::hash(self::RAW_SESSION_ID),
        );
    }

    public function testHashReturnsNullForNull(): void
    {
        self::assertNull(SessionIdHasher::hash(null));
    }

    public function testHashReturnsNullForEmptyString(): void
    {
        self::assertNull(SessionIdHasher::hash(''));
    }

    public function testHashTreatsWhitespaceOnlyInputAsNonEmpty(): void
    {
        self::assertSame(hash('sha256', ' '), SessionIdHasher::hash(' '));
    }

    public function testClassHoldsNoStateAndNoConstructorDependencies(): void
    {
        $ref = new \ReflectionClass(SessionIdHasher::class);

        self::assertTrue($ref->isFinal(), 'SessionIdHasher must be final');
        self::assertSame([], $ref->getProperties(), 'SessionIdHasher must declare no properties');
        $ctor = $ref->getConstructor();
        self::assertTrue(
            $ctor === null || $ctor->getNumberOfParameters() === 0,
            'SessionIdHasher must take no constructor dependencies',
        );
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            self::assertTrue($method->isStatic(), $method->getName() . ' must be static');
        }
    }

    public function testSourceTouchesNoSessionGlobalsOrDatabase(): void
    {
        $file = (new \ReflectionClass(SessionIdHasher::class))->getFileName();
        self::assertIsString($file);
        $source = file_get_contents($file);
        self::assertIsString($source);

        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }

        $forbidden = [
            '$_SESSION', '$_SERVER', '$_COOKIE', '$GLOBALS',
            'session_id', 'session_status', 'mysqli', 'PDO',
            'Repository', 'global ',
        ];
        foreach ($forbidden as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $code,
                "SessionIdHasher code must not reference {$needle}",
            );
        }
    }
}
