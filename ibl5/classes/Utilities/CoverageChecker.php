<?php

declare(strict_types=1);

namespace Utilities;

final class CoverageChecker
{
    public function check(string $cloverFile, float $threshold): CoverageResult
    {
        if (!file_exists($cloverFile)) {
            return CoverageResult::failure(0.0, $threshold, 'Clover XML file not found: ' . $cloverFile);
        }

        $xml = @simplexml_load_file($cloverFile);
        if ($xml === false) {
            return CoverageResult::failure(0.0, $threshold, 'Failed to parse Clover XML: ' . $cloverFile);
        }

        $metricsResult = $xml->xpath('//project/metrics');
        if (!is_array($metricsResult) || $metricsResult === []) {
            return CoverageResult::failure(0.0, $threshold, 'No project metrics found in Clover XML');
        }

        $projectMetrics = reset($metricsResult);
        $statements = (int) ($projectMetrics['statements'] ?? 0);
        $coveredStatements = (int) ($projectMetrics['coveredstatements'] ?? 0);

        if ($statements === 0) {
            return CoverageResult::failure(0.0, $threshold, 'No coverable statements found');
        }

        $percentage = ($coveredStatements / $statements) * 100;

        if ($percentage < $threshold) {
            return CoverageResult::failure($percentage, $threshold, sprintf(
                'Coverage %.2f%% is below threshold %.2f%%',
                $percentage,
                $threshold,
            ));
        }

        return CoverageResult::success($percentage, $threshold);
    }
}
