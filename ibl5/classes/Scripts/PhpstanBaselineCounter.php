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
}
