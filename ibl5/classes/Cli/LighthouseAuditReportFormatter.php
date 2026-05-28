<?php

declare(strict_types=1);

namespace Cli;

final class LighthouseAuditReportFormatter
{
    /**
     * @param list<array{url: string, summary: array<string, float>}> $manifest
     * @return array{title: string, body: string}
     */
    public function format(
        array $manifest,
        ?string $sha = null,
        ?string $workflowUrl = null,
        ?float $durationSeconds = null,
    ): array {
        $date = date('Y-m-d');
        $title = "Lighthouse Audit \u{2014} Week of $date";

        if ($manifest === []) {
            return [
                'title' => $title,
                'body' => "No pages were audited.\n",
            ];
        }

        $ranked = $this->rankByComposite($manifest);
        $flagged = $this->filterFlagged($ranked);

        $totalUrls = count($manifest);
        $errorCount = 0;
        $warnCount = 0;
        $passCount = 0;

        foreach ($ranked as $entry) {
            $status = self::classifyEntry($entry['summary']);
            if ($status === 'error') {
                $errorCount++;
            } elseif ($status === 'warn') {
                $warnCount++;
            } else {
                $passCount++;
            }
        }

        $body = "## Summary\n\n";
        $body .= "- **URLs audited:** $totalUrls\n";
        $body .= "- \u{2705} **Pass:** $passCount\n";
        $body .= "- \u{1F7E1} **Warn:** $warnCount\n";
        $body .= "- \u{1F534} **Error:** $errorCount\n\n";

        if ($flagged !== []) {
            $body .= "## Flagged Pages\n\n";
            $body .= $this->renderTable($flagged);
            $body .= "\n";
        }

        $body .= "## Full Results\n\n";
        $body .= $this->renderTable($ranked);
        $body .= "\n";

        $body .= "## Caveats\n\n";
        $body .= "- Phase-gated modules (Draft, FreeAgency, Waivers) may render a ";
        $body .= "\"not active\" gate page \u{2014} still audited for accessibility/best-practices.\n";
        $body .= "- All pages audited in anonymous context (no authenticated user).\n\n";

        $body .= "---\n\n";
        $footerParts = [];
        if ($sha !== null) {
            $footerParts[] = "Commit: `$sha`";
        }
        if ($workflowUrl !== null) {
            $footerParts[] = "[Workflow run]($workflowUrl)";
        }
        if ($durationSeconds !== null) {
            $minutes = (int) ($durationSeconds / 60);
            $footerParts[] = "Duration: {$minutes}m";
        }
        if ($footerParts !== []) {
            $body .= implode(' | ', $footerParts) . "\n";
        }

        return [
            'title' => $title,
            'body' => $body,
        ];
    }

    /**
     * @param list<array{url: string, summary: array<string, float>}> $manifest
     * @return list<array{url: string, summary: array<string, float>, composite: float}>
     */
    private function rankByComposite(array $manifest): array
    {
        $ranked = [];
        foreach ($manifest as $entry) {
            $scores = [];
            foreach (LighthouseThresholds::CATEGORIES as $cat) {
                if (isset($entry['summary'][$cat])) {
                    $scores[] = $entry['summary'][$cat];
                }
            }
            $composite = $scores !== [] ? array_sum($scores) / count($scores) : 0.0;
            $ranked[] = [
                'url' => $entry['url'],
                'summary' => $entry['summary'],
                'composite' => $composite,
            ];
        }

        usort($ranked, static fn(array $a, array $b): int => $a['composite'] <=> $b['composite']);

        return $ranked;
    }

    /**
     * @param list<array{url: string, summary: array<string, float>, composite: float}> $ranked
     * @return list<array{url: string, summary: array<string, float>, composite: float}>
     */
    private function filterFlagged(array $ranked): array
    {
        return array_values(array_filter(
            $ranked,
            static fn(array $entry): bool => self::classifyEntry($entry['summary']) !== 'pass'
        ));
    }

    /**
     * @param array<string, float> $summary
     */
    private static function classifyEntry(array $summary): string
    {
        foreach (LighthouseThresholds::THRESHOLDS as $category => $threshold) {
            $score = $summary[$category] ?? null;
            if ($score === null) {
                continue;
            }
            if ($threshold['level'] === 'error' && $score < $threshold['minScore']) {
                return 'error';
            }
        }

        foreach (LighthouseThresholds::THRESHOLDS as $category => $threshold) {
            $score = $summary[$category] ?? null;
            if ($score === null) {
                continue;
            }
            if ($score < $threshold['minScore']) {
                return 'warn';
            }
        }

        return 'pass';
    }

    /**
     * @param list<array{url: string, summary: array<string, float>, composite: float}> $entries
     */
    private function renderTable(array $entries): string
    {
        $header = "| URL | Performance | Accessibility | Best Practices | Composite |\n";
        $header .= "|-----|------------|---------------|----------------|----------|\n";

        $rows = '';
        foreach ($entries as $entry) {
            $shortUrl = $this->shortenUrl($entry['url']);
            $cells = [];
            foreach (LighthouseThresholds::CATEGORIES as $category) {
                $score = $entry['summary'][$category] ?? null;
                if ($score === null) {
                    $cells[] = 'n/a';
                    continue;
                }
                $cells[] = $this->formatCell($category, $score);
            }
            $compositeStr = sprintf('%.2f', $entry['composite']);
            $rows .= "| `$shortUrl` | " . implode(' | ', $cells) . " | $compositeStr |\n";
        }

        return $header . $rows;
    }

    private function formatCell(string $category, float $score): string
    {
        $threshold = LighthouseThresholds::THRESHOLDS[$category] ?? null;
        if ($threshold === null) {
            return sprintf('%.2f', $score);
        }

        $scoreStr = sprintf('%.2f', $score);

        $isErrorViolation = $threshold['level'] === 'error'
            && $score < $threshold['minScore'];
        $isWarnViolation = $score < $threshold['minScore'];

        if ($isErrorViolation) {
            return "\u{1F534} " . $scoreStr;
        }
        if ($isWarnViolation) {
            return "\u{1F7E1} " . $scoreStr;
        }

        return $scoreStr;
    }

    private function shortenUrl(string $url): string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

        $short = $path . $query;

        if (str_starts_with($short, '/ibl5')) {
            $short = substr($short, 5);
        }

        return $short !== '' ? $short : '/';
    }
}
