<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Stage counters from one panel build: how many deals were generated, how many
 * each filter dropped, and the real validator's verdicts. Read by the
 * diagnostics dump; the panel does not render it.
 */
final readonly class SuggestionDiagnostics
{
    /**
     * @param array<string, int> $rejections Label => deals dropped, in stage order
     * @param array<string, int> $validatorRejections Validator failure => candidates
     */
    public function __construct(
        public int $generated,
        public int $scored,
        public array $rejections,
        public int $validated,
        public array $validatorRejections,
    ) {
    }
}
