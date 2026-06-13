<?php

declare(strict_types=1);

namespace TradeBlock\Contracts;

/**
 * TradeBlockValidatorInterface - Sanitizes raw POST input for the edit form.
 *
 * Coerces the checkbox list to ints, length-caps free-text notes to the
 * VARCHAR(255) column width, and tolerates malformed input (non-array
 * on_block) without crashing. Ownership/IDOR enforcement is the Processor's
 * job (reconcile against the resolved roster) — the validator only shapes data.
 *
 * @phpstan-type SanitizedEdit array{
 *     pids: list<int>,
 *     notes: array<int, string>,
 *     seekingNote: string
 * }
 */
interface TradeBlockValidatorInterface
{
    public const NOTE_MAX_LENGTH = 255;

    /**
     * @param array<string, mixed> $post
     * @return SanitizedEdit
     */
    public function sanitizeEdit(array $post): array;
}
