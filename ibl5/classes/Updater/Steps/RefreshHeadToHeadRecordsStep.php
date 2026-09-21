<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Rebuild the Head-to-Head Records cache from box-score data.
 *
 * Writes only to DatabaseCache rows — no transaction needed; a cache rebuild
 * that fails must not roll back the import that preceded it.
 *
 * IBL-only — Olympics league does not use this step.
 */
final class RefreshHeadToHeadRecordsStep implements PipelineStepInterface
{
    public function __construct(
        private readonly \mysqli $db,
    ) {
    }

    public function getLabel(): string
    {
        return 'head-to-head records cache refreshed';
    }

    public function execute(): StepResult
    {
        try {
            // dirname(__DIR__, 3) ascends from classes/Updater/Steps → classes/Updater
            // → classes → ibl5 root; the logo directory lives under ibl5/images/logo.
            $ibl5Root = dirname(__DIR__, 3);
            $innerH2hRepo = new \HeadToHeadRecords\HeadToHeadRecordsRepository(
                $this->db,
                null,
                fn (int $id, string $n): string => (new \HeadToHeadRecords\LogoResolver())->resolve($id, $n, $ibl5Root . '/images/logo'),
            );
            $cachedH2hRepo = new \HeadToHeadRecords\CachedHeadToHeadRecordsRepository(
                $innerH2hRepo,
                new \Cache\DatabaseCache($this->db),
            );
            $keys = $cachedH2hRepo->rebuildCache();
        } catch (\Throwable $e) {
            return StepResult::failure($this->getLabel(), $e->getMessage());
        }

        return StepResult::success($this->getLabel(), sprintf('%d keys', $keys));
    }
}
