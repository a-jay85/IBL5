<?php

declare(strict_types=1);

namespace Tests\Support;

trait GoldenSnapshotTrait
{
    protected function snapshotDirectory(): string
    {
        $file = (new \ReflectionClass($this))->getFileName();
        if ($file === false) {
            throw new \LogicException('Cannot resolve the snapshot directory for an internal class.');
        }

        return dirname($file) . '/__snapshots__';
    }

    private function assertSnapshotMatches(string $actual, string $snapshotFile): void
    {
        $directory = $this->snapshotDirectory();
        $snapshotPath = $directory . '/' . $snapshotFile;

        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                self::fail("Could not create snapshot directory {$directory}.");
            }
            if (file_put_contents($snapshotPath, $actual) === false) {
                self::fail("Could not write snapshot {$snapshotPath}.");
            }
            self::assertFileExists($snapshotPath);

            return;
        }

        if (!file_exists($snapshotPath)) {
            self::fail("Snapshot {$snapshotFile} is missing at {$snapshotPath}; run with UPDATE_SNAPSHOTS=1 to create it.");
        }

        $expected = file_get_contents($snapshotPath);
        self::assertSame($expected, $actual, "Snapshot mismatch for {$snapshotFile}. Re-run with UPDATE_SNAPSHOTS=1 to overwrite it, then review the diff.");
    }
}
