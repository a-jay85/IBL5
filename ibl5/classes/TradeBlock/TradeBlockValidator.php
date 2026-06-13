<?php

declare(strict_types=1);

namespace TradeBlock;

use TradeBlock\Contracts\TradeBlockValidatorInterface;

/**
 * @see TradeBlockValidatorInterface
 *
 * @phpstan-import-type SanitizedEdit from TradeBlockValidatorInterface
 */
class TradeBlockValidator implements TradeBlockValidatorInterface
{
    /**
     * @see TradeBlockValidatorInterface::sanitizeEdit()
     *
     * @param array<string, mixed> $post
     * @return SanitizedEdit
     */
    public function sanitizeEdit(array $post): array
    {
        $pids = [];
        $rawOnBlock = $post['on_block'] ?? null;
        if (is_array($rawOnBlock)) {
            foreach ($rawOnBlock as $value) {
                if (is_scalar($value)) {
                    $pid = (int) $value;
                    if ($pid > 0) {
                        $pids[] = $pid;
                    }
                }
            }
        }
        // Drop duplicates while preserving order.
        $pids = array_values(array_unique($pids));

        $notes = [];
        $rawNotes = $post['note'] ?? null;
        if (is_array($rawNotes)) {
            foreach ($rawNotes as $pid => $note) {
                if (!is_scalar($note)) {
                    continue;
                }
                $intPid = (int) $pid;
                if ($intPid > 0) {
                    $notes[$intPid] = $this->cap((string) $note);
                }
            }
        }

        $rawSeeking = $post['seeking_note'] ?? '';
        $seekingNote = is_scalar($rawSeeking) ? $this->cap((string) $rawSeeking) : '';

        return [
            'pids' => $pids,
            'notes' => $notes,
            'seekingNote' => $seekingNote,
        ];
    }

    private function cap(string $value): string
    {
        $trimmed = trim($value);
        return mb_substr($trimmed, 0, self::NOTE_MAX_LENGTH);
    }
}
