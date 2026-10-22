<?php

declare(strict_types=1);

namespace Cli;

/**
 * Names the commit a downloaded Lighthouse baseline manifest was audited at.
 *
 * Input: JSON list of GitHub artifact objects (the `artifacts` output of
 * dawidd6/action-download-artifact). Output: the newest non-expired master
 * artifact's workflow_run.head_sha, or null when no 40-hex sha is available.
 */
final class LighthouseBaselineCommitResolver
{
    public function resolve(string $artifactsJson): ?string
    {
        try {
            $decoded = json_decode($artifactsJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($decoded) || !array_is_list($decoded)) {
            return null;
        }

        $newest = null;
        $newestCreatedAt = '';
        foreach ($decoded as $artifact) {
            if (!is_array($artifact)
                || ($artifact['expired'] ?? null) !== false
                || !is_string($artifact['created_at'] ?? null)
                || !is_array($artifact['workflow_run'] ?? null)
                || ($artifact['workflow_run']['head_branch'] ?? null) !== 'master'
            ) {
                continue;
            }
            if ($newest === null || strcmp($artifact['created_at'], $newestCreatedAt) > 0) {
                $newest = $artifact;
                $newestCreatedAt = $artifact['created_at'];
            }
        }
        if ($newest === null) {
            return null;
        }

        $sha = $newest['workflow_run']['head_sha'] ?? null;
        return is_string($sha) && preg_match('/^[0-9a-f]{40}$/', $sha) === 1 ? $sha : null;
    }
}
