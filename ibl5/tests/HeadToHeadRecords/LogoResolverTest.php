<?php

declare(strict_types=1);

namespace Tests\HeadToHeadRecords;

use HeadToHeadRecords\LogoResolver;
use PHPUnit\Framework\TestCase;

final class LogoResolverTest extends TestCase
{
    private LogoResolver $resolver;
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->resolver = new LogoResolver();
        $this->fixtureDir = sys_get_temp_dir() . '/logo_resolver_test_' . uniqid('', true);
        mkdir($this->fixtureDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Remove any files we created, then the directory.
        foreach (glob($this->fixtureDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->fixtureDir);
    }

    /**
     * When the era-specific file (new{id}({name}).png) exists, it is returned first.
     */
    public function testPrefersEraSpecificFile(): void
    {
        touch($this->fixtureDir . '/new5(Raptors).png');
        touch($this->fixtureDir . '/Raptors.png');
        touch($this->fixtureDir . '/new5.png');

        $result = $this->resolver->resolve(5, 'Raptors', $this->fixtureDir);

        $this->assertSame('new5(Raptors).png', $result);
    }

    /**
     * When only the legacy name file exists, it is returned as the second candidate.
     */
    public function testFallsBackToLegacyNameFile(): void
    {
        touch($this->fixtureDir . '/Raptors.png');
        // new5(Raptors).png and new5.png are intentionally absent.

        $result = $this->resolver->resolve(5, 'Raptors', $this->fixtureDir);

        $this->assertSame('Raptors.png', $result);
    }

    /**
     * When no matching file exists in the fixture dir, new{id}.png is returned.
     */
    public function testFallsBackToCurrentLogoWhenNothingExists(): void
    {
        // Directory is empty — no logo file present.
        $result = $this->resolver->resolve(7, 'Wolves', $this->fixtureDir);

        $this->assertSame('new7.png', $result);
    }

    /**
     * The resolver never probes .jpg files — even when a .jpg is the only file present.
     */
    public function testNeverReturnsJpg(): void
    {
        touch($this->fixtureDir . '/new16(Kings).jpg');
        touch($this->fixtureDir . '/Kings.jpg');

        $result = $this->resolver->resolve(16, 'Kings', $this->fixtureDir);

        // No .png files exist — must fall back to the bare new{id}.png name,
        // and the returned string must not end in .jpg.
        $this->assertStringEndsWith('.png', $result);
        $this->assertStringNotContainsString('.jpg', $result);
    }

    /**
     * Path-separator characters in teamName are stripped so the resolved filename
     * contains no directory separator and cannot escape $imageRoot.
     */
    public function testStripsPathSeparatorsFromTeamName(): void
    {
        $result = $this->resolver->resolve(22, '../etc', $this->fixtureDir);

        // Must be a bare filename — no forward slash, no backslash.
        $this->assertStringNotContainsString('/', $result);
        $this->assertStringNotContainsString('\\', $result);
        $this->assertStringEndsWith('.png', $result);
    }
}
