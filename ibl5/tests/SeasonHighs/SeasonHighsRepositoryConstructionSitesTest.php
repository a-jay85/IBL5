<?php

declare(strict_types=1);

namespace Tests\SeasonHighs;

use PHPUnit\Framework\TestCase;

/**
 * Structural guard: every production construction of SeasonHighsRepository
 * must be the first argument of a CachedSeasonHighsRepository. A bare
 * construction silently bypasses the DB cache.
 */
final class SeasonHighsRepositoryConstructionSitesTest extends TestCase
{
    private const CONSTRUCTION_PATTERN = '/new\s+\\\\?(?:SeasonHighs\\\\)?SeasonHighsRepository\s*\(/';
    private const WRAPPED_PATTERN = '/new\s+\\\\?(?:SeasonHighs\\\\)?CachedSeasonHighsRepository\s*\(\s*$/';

    /**
     * Restoring the bare construction in the SeasonHighs module factory
     * is reported as classes/Module/Factories/SeasonHighsFactory.php.
     */
    public function testEveryProductionConstructionIsWrappedInCachedDecorator(): void
    {
        $root = dirname(__DIR__, 2);
        $violations = [];
        $total = 0;

        foreach ($this->productionPhpFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);

            $total += preg_match_all(self::CONSTRUCTION_PATTERN, $source);

            foreach ($this->findBareConstructions($source) as $line) {
                $violations[] = substr($file, strlen($root) + 1) . ':' . $line;
            }
        }

        $this->assertGreaterThanOrEqual(1, $total, 'Scan found no SeasonHighsRepository construction; scan root or pattern is broken.');
        $this->assertSame(
            [],
            $violations,
            'Bare SeasonHighsRepository construction bypasses the DB cache. Wrap it in new CachedSeasonHighsRepository(...) at: '
                . implode(', ', $violations)
        );
    }

    public function testScannerFlagsBareConstruction(): void
    {
        $source = "<?php\n\$r = new SeasonHighsRepository(\$db, \$ctx);\n";

        $this->assertSame([2], $this->findBareConstructions($source));
    }

    public function testScannerFlagsFullyQualifiedBareConstruction(): void
    {
        $source = "<?php\n\$r = new \\SeasonHighs\\SeasonHighsRepository(\$db);\n";

        $this->assertCount(1, $this->findBareConstructions($source));
    }

    public function testScannerAcceptsWrappedConstruction(): void
    {
        $multiLine = "<?php\n\$r = new \\SeasonHighs\\CachedSeasonHighsRepository(\n"
            . "    new SeasonHighsRepository(\$db, \$ctx),\n"
            . "    new \\Cache\\DatabaseCache(\$db),\n"
            . "    'ibl'\n);\n";
        $oneLine = "<?php\n\$r = new CachedSeasonHighsRepository(new SeasonHighsRepository(\$db, \$ctx), \$cache, 'ibl');\n";

        $this->assertSame([], $this->findBareConstructions($multiLine));
        $this->assertSame([], $this->findBareConstructions($oneLine));
    }

    /**
     * @return list<int> 1-based line numbers of unwrapped constructions
     */
    private function findBareConstructions(string $source): array
    {
        preg_match_all(self::CONSTRUCTION_PATTERN, $source, $matches, PREG_OFFSET_CAPTURE);

        $lines = [];
        foreach ($matches[0] as $match) {
            $offset = $match[1];
            $before = substr($source, max(0, $offset - 300), min(300, $offset));

            if (preg_match(self::WRAPPED_PATTERN, $before) === 1) {
                continue;
            }

            $lines[] = substr_count(substr($source, 0, $offset), "\n") + 1;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function productionPhpFiles(): array
    {
        $files = [];

        foreach (['classes', 'modules'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/' . $dir, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $info */
            foreach ($iterator as $info) {
                if ($info->isFile() && $info->getExtension() === 'php') {
                    $files[] = $info->getPathname();
                }
            }
        }

        return $files;
    }
}
