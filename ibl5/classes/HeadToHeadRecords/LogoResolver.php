<?php

declare(strict_types=1);

namespace HeadToHeadRecords;

/**
 * LogoResolver - Resolves a logo filename for a franchise/team combination.
 *
 * Probes candidates in priority order and returns the first match found in
 * $imageRoot. When no file exists, falls back to the era-keyed filename.
 * Only probes .png files (never .jpg).
 */
final class LogoResolver
{
    /**
     * Resolve the best logo filename for the given franchise and team name.
     *
     * Candidate order:
     *   1. new{$franchiseId}({$teamName}).png  — era-specific with team name
     *   2. {$teamName}.png                      — legacy name-based file
     *   3. new{$franchiseId}.png                — current era logo (fallback)
     *
     * Returns the first candidate for which a file exists under $imageRoot.
     * Falls back to new{$franchiseId}.png when nothing matches.
     *
     * Returns a bare filename — never a path. Only probes .png; .jpg files
     * are excluded by design.
     *
     * @param int    $franchiseId  Numeric franchise/era identifier.
     * @param string $teamName     Human-readable team name; path separators are stripped.
     * @param string $imageRoot    Absolute path to the directory containing logo files.
     * @return string Bare filename (no directory component).
     */
    public function resolve(int $franchiseId, string $teamName, string $imageRoot): string
    {
        // Strip path-traversal characters from teamName before composing candidates.
        $safeName = str_replace(['..', '/', '\\'], '', $teamName);

        $candidates = [
            "new{$franchiseId}({$safeName}).png",
            "{$safeName}.png",
            "new{$franchiseId}.png",
        ];

        foreach ($candidates as $candidate) {
            // Apply basename as a second safety layer so no composed candidate can
            // escape $imageRoot even if $safeName contained unusual characters.
            $candidate = basename($candidate);
            if (is_file($imageRoot . '/' . $candidate)) {
                return $candidate;
            }
        }

        return "new{$franchiseId}.png";
    }
}
