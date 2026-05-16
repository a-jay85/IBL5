<?php

declare(strict_types=1);

namespace Scripts;

final class PhpstanBaselineCounter
{
    /**
     * @return array<string, int> identifier => number of baseline entries with that identifier
     */
    public function countByIdentifier(string $baselinePath): array
    {
        if (!file_exists($baselinePath)) {
            throw new \RuntimeException("Baseline file not found: {$baselinePath}");
        }

        $content = file_get_contents($baselinePath);
        if ($content === false) {
            throw new \RuntimeException("Could not read baseline file: {$baselinePath}");
        }

        $counts = [];

        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^\s+identifier:\s+(\S+)/', $line, $idMatch) === 1) {
                $identifier = $idMatch[1];
                $counts[$identifier] = ($counts[$identifier] ?? 0) + 1;
            }
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @return array<string, int> path => count of entries with the given identifier
     */
    public function countByPathForIdentifier(string $baselinePath, string $targetIdentifier): array
    {
        if (!file_exists($baselinePath)) {
            throw new \RuntimeException("Baseline file not found: {$baselinePath}");
        }

        $content = file_get_contents($baselinePath);
        if ($content === false) {
            throw new \RuntimeException("Could not read baseline file: {$baselinePath}");
        }

        $counts = [];
        $lines = explode("\n", $content);
        $lineCount = count($lines);

        for ($i = 0; $i < $lineCount; $i++) {
            if (preg_match('/^\s+identifier:\s+(\S+)/', $lines[$i], $idMatch) !== 1) {
                continue;
            }
            if ($idMatch[1] !== $targetIdentifier) {
                continue;
            }

            $entryCount = 1;
            if ($i + 1 < $lineCount && preg_match('/^\s+count:\s+(\d+)/', $lines[$i + 1], $countMatch) === 1) {
                $entryCount = (int) $countMatch[1];
            }
            if ($i + 2 < $lineCount && preg_match('/^\s+path:\s+(.+)/', $lines[$i + 2], $pathMatch) === 1) {
                $path = trim($pathMatch[1]);
                $counts[$path] = ($counts[$path] ?? 0) + $entryCount;
            }
        }

        return $counts;
    }
}
