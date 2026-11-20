<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning;

use TradeSuggestions\Feedback\HideReason;

/**
 * One stored hide, read for the tuning page. Every string is raw, exactly as
 * stored; the view escapes it.
 */
final readonly class TuningFeedback
{
    /**
     * @param list<string> $reasonCodes the stored comma-join, split
     */
    public function __construct(
        public int $tid,
        public int $partnerTid,
        public string $dealKey,
        public string $coreKey,
        public string $dealJson,
        public array $reasonCodes,
        public ?string $freeText,
        public string $createdAt,
    ) {
    }

    /**
     * Owner-facing labels. An unknown code (one retired later) keeps its raw text.
     *
     * @return list<string>
     */
    public function reasonLabels(): array
    {
        return array_map(
            static fn (string $code): string => HideReason::tryFrom($code)?->label() ?? $code,
            $this->reasonCodes,
        );
    }
}
