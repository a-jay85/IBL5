<?php

declare(strict_types=1);

namespace Tests\Cli;

use Cli\LighthouseUrls;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('cli')]
final class LighthousePrUrlsTest extends TestCase
{
    private const BASE = 'http://localhost:8080';

    private string $scriptPath;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/lighthouse-pr-urls');
        self::assertNotFalse($resolved, 'bin/lighthouse-pr-urls must exist');
        $this->scriptPath = $resolved;
    }

    public function testSingleModuleFileMapsToModuleUrlPlusIndex(): void
    {
        $urls = $this->runWithStdin("ibl5/modules/Team/foo.php\n");

        self::assertContains(self::BASE . '/ibl5/index.php', $urls);
        self::assertContains(self::BASE . '/ibl5/modules.php?name=Team&op=team&teamid=1', $urls);
    }

    public function testTwoFilesInSameModuleDedupeToOneUrl(): void
    {
        $urls = $this->runWithStdin("ibl5/modules/Team/foo.php\nibl5/modules/Team/bar.php\n");

        $teamUrls = array_filter(
            $urls,
            static fn (string $u): bool => str_contains($u, 'name=Team')
        );
        self::assertCount(1, $teamUrls, 'two files in one module → exactly one module URL');
    }

    public function testGlobalCssFallsBackToRepresentativeSet(): void
    {
        self::assertSame(
            $this->representative(),
            $this->runWithStdin("ibl5/css/input.css\n")
        );

        // A module name embedded in a CSS path must NOT be substring-matched.
        self::assertSame(
            $this->representative(),
            $this->runWithStdin("ibl5/design/components/team-page.css\n")
        );
    }

    public function testClassChangeFallsBackToRepresentativeSet(): void
    {
        self::assertSame(
            $this->representative(),
            $this->runWithStdin("ibl5/classes/Foo.php\n")
        );
    }

    public function testNineModulesExceedCapAndFallBackToRepresentativeSet(): void
    {
        $paths = implode("\n", [
            'ibl5/modules/ActivityTracker/a.php',
            'ibl5/modules/AllStarAppearances/a.php',
            'ibl5/modules/ApiKeys/a.php',
            'ibl5/modules/AwardHistory/a.php',
            'ibl5/modules/CapSpace/a.php',
            'ibl5/modules/CareerLeaderboards/a.php',
            'ibl5/modules/ComparePlayers/a.php',
            'ibl5/modules/ContractList/a.php',
            'ibl5/modules/DebugMenu/a.php',
        ]) . "\n";

        self::assertSame($this->representative(), $this->runWithStdin($paths));
    }

    public function testEightModulesStayModuleSpecific(): void
    {
        $paths = implode("\n", [
            'ibl5/modules/ActivityTracker/a.php',
            'ibl5/modules/AllStarAppearances/a.php',
            'ibl5/modules/ApiKeys/a.php',
            'ibl5/modules/AwardHistory/a.php',
            'ibl5/modules/CapSpace/a.php',
            'ibl5/modules/CareerLeaderboards/a.php',
            'ibl5/modules/ComparePlayers/a.php',
            'ibl5/modules/ContractList/a.php',
        ]) . "\n";

        $urls = $this->runWithStdin($paths);

        self::assertNotSame($this->representative(), $urls);
        self::assertCount(9, $urls, '8 module URLs + index');
        self::assertContains(self::BASE . '/ibl5/index.php', $urls);
    }

    public function testUnknownModuleDirIsSkippedThenFallsBack(): void
    {
        $urls = $this->runWithStdin("ibl5/modules/NotARealModule/x.php\n");

        foreach ($urls as $u) {
            self::assertStringNotContainsString('name=NotARealModule', $u);
        }
        self::assertSame($this->representative(), $urls);
    }

    public function testEmptyStdinFallsBackToRepresentativeSet(): void
    {
        self::assertSame($this->representative(), $this->runWithStdin(''));
    }

    public function testDocOnlyDiffFallsBackToRepresentativeSet(): void
    {
        self::assertSame($this->representative(), $this->runWithStdin("ibl5/docs/foo.md\n"));
    }

    /** @return list<string> */
    private function representative(): array
    {
        return LighthouseUrls::representativeUrls(self::BASE);
    }

    /**
     * Pipe newline-delimited changed paths to the script's stdin via --stdin
     * and return the decoded JSON URL list.
     *
     * @return list<string>
     */
    private function runWithStdin(string $paths): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open(
            [$this->scriptPath, '--stdin', '--json', '--base-url=' . self::BASE],
            $descriptors,
            $pipes
        );
        self::assertIsResource($proc, 'proc_open must start the script');

        fwrite($pipes[0], $paths);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        self::assertSame(0, $exit, 'Script should exit 0. Stderr: ' . (string) $stderr);

        $decoded = json_decode((string) $stdout, true);
        self::assertIsArray($decoded, 'Output must be a JSON array. Got: ' . (string) $stdout);
        /** @var list<string> $decoded */
        return $decoded;
    }
}
