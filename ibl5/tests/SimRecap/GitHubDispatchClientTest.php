<?php

declare(strict_types=1);

namespace Tests\SimRecap;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use SimRecap\GitHubDispatchClient;

/**
 * @covers \SimRecap\GitHubDispatchClient
 */
class GitHubDispatchClientTest extends TestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $files = glob($dir . '/*');
            foreach ($files === false ? [] : $files as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
        $this->tempDirs = [];
    }

    public function testDispatchPostsRepositoryDispatchWithSimAsString(): void
    {
        $calls = [];
        $client = new GitHubDispatchClient(
            't0k',
            'a-jay85/IBL5',
            'sim-recap',
            static function (string $url, array $headers, string $body) use (&$calls): int {
                $calls[] = ['url' => $url, 'headers' => $headers, 'body' => $body];
                return 204;
            },
        );

        self::assertTrue($client->dispatch(731));
        self::assertCount(1, $calls);
        self::assertSame('https://api.github.com/repos/a-jay85/IBL5/dispatches', $calls[0]['url']);
        self::assertSame(
            ['event_type' => 'sim-recap', 'client_payload' => ['sim' => '731']],
            json_decode($calls[0]['body'], true),
        );
        self::assertContains('Authorization: Bearer t0k', $calls[0]['headers']);
        self::assertContains('Accept: application/vnd.github+json', $calls[0]['headers']);
    }

    public function testDispatchReturnsFalseOnNon204WithoutThrowing(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: string, message: string, context: array<mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
            }
        };
        $client = new GitHubDispatchClient(
            't0k',
            'a-jay85/IBL5',
            'sim-recap',
            static fn (string $url, array $headers, string $body): int => 401,
            $logger,
        );

        self::assertFalse($client->dispatch(731));
        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertStringNotContainsString('t0k', (string) json_encode($logger->records[0]['context']));
    }

    public function testDispatchReturnsFalseWhenTransportThrows(): void
    {
        $client = new GitHubDispatchClient(
            't0k',
            'a-jay85/IBL5',
            'sim-recap',
            static function (string $url, array $headers, string $body): int {
                throw new \RuntimeException('boom');
            },
        );

        self::assertFalse($client->dispatch(731));
    }

    public function testDispatchReturnsFalseOnTransportErrorStatusZero(): void
    {
        $client = new GitHubDispatchClient(
            't0k',
            'a-jay85/IBL5',
            'sim-recap',
            static fn (string $url, array $headers, string $body): int => 0,
        );

        self::assertFalse($client->dispatch(731));
    }

    public function testFromConfigReturnsNullWhenTokenEmpty(): void
    {
        $calls = 0;
        $dir = $this->makeConfigDir('');

        $client = GitHubDispatchClient::fromConfig(
            $dir,
            static function (string $url, array $headers, string $body) use (&$calls): int {
                $calls++;
                return 204;
            },
        );

        self::assertNull($client);
        self::assertSame(0, $calls);
    }

    public function testFromConfigReturnsNullWhenFileAbsent(): void
    {
        self::assertNull(GitHubDispatchClient::fromConfig($this->makeTempDir()));
    }

    public function testFromConfigBuildsClientFromFile(): void
    {
        $calls = [];
        $dir = $this->makeConfigDir('abc');

        $client = GitHubDispatchClient::fromConfig(
            $dir,
            static function (string $url, array $headers, string $body) use (&$calls): int {
                $calls[] = $headers;
                return 204;
            },
        );

        self::assertInstanceOf(GitHubDispatchClient::class, $client);
        self::assertTrue($client->dispatch(5));
        self::assertCount(1, $calls);
        self::assertContains('Authorization: Bearer abc', $calls[0]);
    }

    public function testDebugInfoOmitsToken(): void
    {
        $client = GitHubDispatchClient::fromConfig($this->makeConfigDir('abc'));

        self::assertNotNull($client);
        self::assertStringNotContainsString('abc', print_r($client, true));
    }

    public function testClientHoldsNoDbHandleOrActorIdentity(): void
    {
        self::assertSame([], self::findDbOrIdentityViolations(new \ReflectionClass(GitHubDispatchClient::class)));
    }

    public function testReflectionGuardRejectsDbHandleProperty(): void
    {
        $withDb = new class (self::createStub(\mysqli::class)) {
            public function __construct(private \mysqli $db)
            {
            }

            public function db(): \mysqli
            {
                return $this->db;
            }
        };

        $violations = self::findDbOrIdentityViolations(new \ReflectionClass($withDb));

        self::assertNotSame([], $violations);
        self::assertTrue(
            in_array(true, array_map(static fn (string $v): bool => str_contains($v, 'mysqli'), $violations), true),
            'expected a mysqli violation, got: ' . implode('; ', $violations),
        );
    }

    /**
     * Reflection predicate: reports every way a class holds a DB handle or actor identity.
     *
     * @param \ReflectionClass<object> $class
     * @return list<string>
     */
    private static function findDbOrIdentityViolations(\ReflectionClass $class): array
    {
        $violations = [];

        if (!$class->isAnonymous() && !$class->isFinal()) {
            $violations[] = 'class is not final';
        }

        $isForbiddenType = static function (?\ReflectionType $type): bool {
            if ($type === null) {
                return false;
            }
            $names = $type instanceof \ReflectionNamedType
                ? [$type->getName()]
                : array_map(
                    static fn (\ReflectionType $t): string => $t instanceof \ReflectionNamedType ? $t->getName() : '',
                    $type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType
                        ? $type->getTypes()
                        : [],
                );
            foreach ($names as $name) {
                if (strcasecmp(ltrim($name, '\\'), 'mysqli') === 0) {
                    return true;
                }
            }
            return false;
        };
        $isForbiddenName = static fn (string $name): bool => preg_match('/teamid|session/i', $name) === 1;

        foreach ($class->getProperties() as $property) {
            if ($isForbiddenType($property->getType())) {
                $violations[] = 'property $' . $property->getName() . ' is typed mysqli';
            }
            if ($isForbiddenName($property->getName())) {
                $violations[] = 'property $' . $property->getName() . ' names a TeamID/session identity';
            }
        }

        $constructor = $class->getConstructor();
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                if ($isForbiddenType($parameter->getType())) {
                    $violations[] = 'constructor parameter $' . $parameter->getName() . ' is typed mysqli';
                }
                if ($isForbiddenName($parameter->getName())) {
                    $violations[] = 'constructor parameter $' . $parameter->getName() . ' names a TeamID/session identity';
                }
            }
        }

        $file = $class->getFileName();
        if (!$class->isAnonymous() && $file !== false) {
            $source = (string) file_get_contents($file);
            if (preg_match('/^\s*use\s+\\\\?(Security|Auth)\\\\/m', $source) === 1) {
                $violations[] = 'source imports a Security or Auth namespace';
            }
            if (preg_match('/\\\\(Security|Auth)\\\\/', $source) === 1) {
                $violations[] = 'source references a Security or Auth namespace';
            }
        }

        return $violations;
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/ghdispatch_' . bin2hex(random_bytes(6));
        mkdir($dir);
        $this->tempDirs[] = $dir;
        return $dir;
    }

    private function makeConfigDir(string $token): string
    {
        $dir = $this->makeTempDir();
        file_put_contents(
            $dir . '/github-dispatch.config.php',
            '<?php return ' . var_export([
                'token' => $token,
                'repo' => 'a-jay85/IBL5',
                'event_type' => 'sim-recap',
            ], true) . ';',
        );
        return $dir;
    }
}
