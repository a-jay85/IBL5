<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

trait SnapshotTestTrait
{
    private function assertSnapshotMatches(string $actual, string $snapshotFile): void
    {
        $snapshotPath = __DIR__ . '/__snapshots__/' . $snapshotFile;

        if (!file_exists($snapshotPath)) {
            file_put_contents($snapshotPath, $actual);
            $this->assertFileExists($snapshotPath);
            return;
        }

        $expected = file_get_contents($snapshotPath);
        $this->assertSame($expected, $actual, "Snapshot mismatch for {$snapshotFile}. Delete the snapshot file to regenerate.");
    }
}
