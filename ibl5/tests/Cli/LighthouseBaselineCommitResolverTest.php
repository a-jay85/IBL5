<?php

declare(strict_types=1);

namespace Tests\Cli;

use Cli\LighthouseBaselineCommitResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('cli')]
final class LighthouseBaselineCommitResolverTest extends TestCase
{
    private LighthouseBaselineCommitResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new LighthouseBaselineCommitResolver();
    }

    /**
     * Catches: resolve() returning null unconditionally, or reading head_sha from the wrong key.
     */
    public function testResolveReturnsHeadShaOfSingleArtifact(): void
    {
        $json = $this->encode([$this->artifact('2026-10-10T06:00:00Z', str_repeat('a', 40))]);

        self::assertSame(str_repeat('a', 40), $this->resolver->resolve($json));
    }

    /**
     * Catches: dropping the strcmp(...) > 0 comparison (first or last wins instead).
     */
    public function testResolvePicksNewestNonExpiredMasterArtifact(): void
    {
        $json = $this->encode([
            $this->artifact('2026-10-05T06:00:00Z', str_repeat('a', 40)),
            $this->artifact('2026-10-12T06:00:00Z', str_repeat('b', 40)),
            $this->artifact('2026-10-08T06:00:00Z', str_repeat('c', 40)),
        ]);

        self::assertSame(str_repeat('b', 40), $this->resolver->resolve($json));
    }

    /**
     * Catches: deleting the expired filter.
     */
    public function testResolveSkipsExpiredArtifact(): void
    {
        $json = $this->encode([
            $this->artifact('2026-10-05T06:00:00Z', str_repeat('a', 40)),
            $this->artifact('2026-10-12T06:00:00Z', str_repeat('b', 40), true),
        ]);

        self::assertSame(str_repeat('a', 40), $this->resolver->resolve($json));
    }

    /**
     * Catches: deleting the head_branch === 'master' filter.
     */
    public function testResolveSkipsNonMasterArtifact(): void
    {
        $json = $this->encode([
            $this->artifact('2026-10-05T06:00:00Z', str_repeat('a', 40)),
            $this->artifact('2026-10-12T06:00:00Z', str_repeat('b', 40), false, 'feature-x'),
        ]);

        self::assertSame(str_repeat('a', 40), $this->resolver->resolve($json));
    }

    /**
     * Catches: removing the preg_match guard, or dropping its ^/$ anchors (the 41-char case).
     */
    #[DataProvider('invalidShaProvider')]
    public function testResolveRejectsInvalidSha(mixed $sha): void
    {
        $json = $this->encode([$this->artifact('2026-10-10T06:00:00Z', $sha)]);

        self::assertNull($this->resolver->resolve($json));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidShaProvider(): array
    {
        return [
            'uppercase' => [str_repeat('A', 40)],
            '39 hex chars' => [str_repeat('a', 39)],
            '41 hex chars' => [str_repeat('a', 41)],
            'backtick' => [str_repeat('a', 39) . '`'],
            'int' => [12345],
        ];
    }

    /**
     * Catches: validating the sha inside the loop filter, which makes the older artifact win.
     */
    public function testResolveDoesNotFallBackToOlderArtifactWhenNewestShaInvalid(): void
    {
        $json = $this->encode([
            $this->artifact('2026-10-05T06:00:00Z', str_repeat('a', 40)),
            $this->artifact('2026-10-12T06:00:00Z', 'not-a-sha'),
        ]);

        self::assertNull($this->resolver->resolve($json));
    }

    /**
     * Catches: a reset()/end() on an empty list returning false cast through.
     */
    public function testResolveReturnsNullForEmptyList(): void
    {
        self::assertNull($this->resolver->resolve('[]'));
    }

    /**
     * Catches: removing the try/catch (a JsonException escapes).
     */
    public function testResolveReturnsNullForMalformedJson(): void
    {
        self::assertNull($this->resolver->resolve('{not json'));
    }

    /**
     * Catches: removing the array_is_list check. Pins the one-shape contract.
     */
    public function testResolveReturnsNullForListApiWrapperObject(): void
    {
        $json = $this->encode([
            'total_count' => 1,
            'artifacts' => [$this->artifact('2026-10-10T06:00:00Z', str_repeat('a', 40))],
        ]);

        self::assertNull($this->resolver->resolve($json));
    }

    /**
     * Catches: removing the is_array($artifact) or is_array($artifact['workflow_run'] ?? null) guards.
     */
    public function testResolveSkipsMalformedElements(): void
    {
        $json = $this->encode([
            'x',
            new \stdClass(),
            ['expired' => false, 'created_at' => '2026-10-11T06:00:00Z'],
            $this->artifact('2026-10-10T06:00:00Z', str_repeat('a', 40)),
        ]);

        self::assertSame(str_repeat('a', 40), $this->resolver->resolve($json));
    }

    /**
     * @return array<string, mixed>
     */
    private function artifact(string $createdAt, mixed $sha, bool $expired = false, string $branch = 'master'): array
    {
        return [
            'id' => 1,
            'name' => 'lighthouse-baseline-manifest',
            'expired' => $expired,
            'created_at' => $createdAt,
            'workflow_run' => ['id' => 2, 'head_branch' => $branch, 'head_sha' => $sha],
        ];
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
