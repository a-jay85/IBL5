<?php

declare(strict_types=1);

namespace Cli;

final class LighthouseCommentFormatter
{

    /**
     * @param list<array{url: string, summary: array<string, float>}> $manifest
     * @param array<string, string> $links
     * @param list<array{url: string, summary: array<string, float>}>|null $baseline
     * @return array{markdown: string, hasErrorViolation: bool}
     */
    public function format(array $manifest, array $links = [], ?array $baseline = null): array
    {
        if ($manifest === []) {
            return [
                'markdown' => $this->wrapComment(
                    "Lighthouse skipped \u{2014} no source-relevant changes.\n"
                ),
                'hasErrorViolation' => false,
            ];
        }

        $baselineByUrl = [];
        if ($baseline !== null) {
            foreach ($baseline as $entry) {
                $baselineByUrl[$entry['url']] = $entry['summary'];
            }
        }

        $hasBaseline = $baselineByUrl !== [];
        $hasErrorViolation = false;

        $header = "| URL | Performance | Accessibility | Best Practices | Report |\n";
        $header .= "|-----|------------|---------------|----------------|--------|\n";

        $rows = '';
        foreach ($manifest as $entry) {
            $url = $entry['url'];
            $summary = $entry['summary'];
            $baselineScores = $baselineByUrl[$url] ?? null;

            $shortUrl = $this->shortenUrl($url);
            $reportLink = isset($links[$url]) ? '[view ↗](' . $links[$url] . ')' : '';

            $cells = [];
            foreach (LighthouseThresholds::CATEGORIES as $category) {
                $score = $summary[$category] ?? null;
                if ($score === null) {
                    $cells[] = 'n/a';
                    continue;
                }

                $delta = null;
                if ($baselineScores !== null && isset($baselineScores[$category])) {
                    $delta = $score - $baselineScores[$category];
                }

                $cells[] = $this->formatCell($category, $score, $delta);

                $threshold = LighthouseThresholds::THRESHOLDS[$category];
                if ($threshold['level'] === 'error' && $score < $threshold['minScore']) {
                    $hasErrorViolation = true;
                }
            }

            $rows .= "| `$shortUrl` | " . implode(' | ', $cells) . " | $reportLink |\n";
        }

        $body = "## \u{1F6A6} Lighthouse Audit\n\n";
        $body .= $header . $rows . "\n";
        $body .= "**Thresholds:** performance \u{2265} 0.60 (warn), ";
        $body .= "accessibility \u{2265} 0.85 (error), ";
        $body .= "best-practices \u{2265} 0.80 (warn).\n\n";

        if (!$hasBaseline) {
            $body .= "No master baseline yet \u{2014} deltas will appear after the next master push.\n\n";
        }

        $body .= "\u{1F7E1} = below warn threshold or regression > 0.03 vs baseline\n";
        $body .= "\u{1F534} = below error threshold\n";

        // Clean audits (no error-level violation) collapse under <details> to save
        // PR scroll space; failing audits stay expanded. The wrapComment() marker
        // stays outside the <details> so sticky-comment detection still finds it.
        $markdown = $hasErrorViolation
            ? $body
            : "<details><summary>\u{2705} Lighthouse Audit \u{2014} no failing audits</summary>\n\n"
                . $body
                . "\n</details>\n";

        return [
            'markdown' => $this->wrapComment($markdown),
            'hasErrorViolation' => $hasErrorViolation,
        ];
    }

    /**
     * @param key-of<LighthouseThresholds::THRESHOLDS> $category
     */
    private function formatCell(string $category, float $score, ?float $delta): string
    {
        $threshold = LighthouseThresholds::THRESHOLDS[$category];
        $scoreStr = sprintf('%.2f', $score);

        $isErrorViolation = $threshold['level'] === 'error'
            && $score < $threshold['minScore'];

        $isWarnViolation = $threshold['level'] === 'warn'
            && $score < $threshold['minScore'];

        $isRegression = $delta !== null && $delta < -LighthouseThresholds::REGRESSION_THRESHOLD;

        if ($isErrorViolation) {
            $marker = "\u{1F534} ";
        } elseif ($isWarnViolation || $isRegression) {
            $marker = "\u{1F7E1} ";
        } else {
            $marker = '';
        }

        if ($delta !== null) {
            $deltaStr = $this->formatDelta($delta);
            return $marker . $scoreStr . ' (' . $deltaStr . ')';
        }

        return $marker . $scoreStr;
    }

    private function formatDelta(float $delta): string
    {
        if (abs($delta) < 0.005) {
            return "\u{00B1}0.00";
        }

        $sign = $delta > 0 ? '+' : '';
        return $sign . sprintf('%.2f', $delta);
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

    private function wrapComment(string $body): string
    {
        return "<!-- lighthouse-comment -->\n" . $body;
    }
}
