<?php

declare(strict_types=1);

namespace Updater;

/**
 * Where a JSB file's bytes actually came from on this read.
 */
final class SourceProvenance
{
    public const KIND_ARCHIVE = 'archive';
    public const KIND_DISK = 'disk';

    /**
     * @param list<string> $selectionWarnings Operator-readable warnings from ArchiveSelection::warnings().
     *                                        Empty for disk-fallback reads and when no detectors fire.
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $name,
        public readonly ?string $declaredSeason = null,
        public readonly ?int $declaredSeasonEndingYear = null,
        public readonly ?string $declaredPhase = null,
        public readonly array $selectionWarnings = [],
    ) {
    }

    public function isProperlyNamed(): bool
    {
        return $this->declaredSeasonEndingYear !== null;
    }

    public function describe(): string
    {
        return $this->kind === self::KIND_DISK
            ? 'disk file ' . $this->name
            : 'archive ' . $this->name;
    }
}
