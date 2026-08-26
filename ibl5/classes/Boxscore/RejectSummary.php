<?php

declare(strict_types=1);

namespace Boxscore;

final class RejectSummary
{
    public const DEFAULT_TRIPLE_LIMIT = 25;
    public const DISCORD_TRIPLE_LIMIT = 10;
    public const DISCORD_MAX_CHARS = 1900;

    /**
     * @param list<RejectedGame> $rejects
     * @param array<string,int>  $reasonCounts
     */
    private function __construct(
        public readonly int $count,
        public readonly ?string $firstDate,
        public readonly ?string $lastDate,
        /** @var array<string,int> reason code => how many rejects carried it */
        public readonly array $reasonCounts,
        /** @var list<RejectedGame> */
        public readonly array $rejects,
        public readonly ?string $sourceArchive,
        public readonly ?int $recordedCount,
    ) {
    }

    /** @param list<RejectedGame> $rejects */
    public static function fromRejects(array $rejects, ?string $sourceArchive = null, ?int $recordedCount = null): self
    {
        if ($rejects === []) {
            return new self(
                count: 0,
                firstDate: null,
                lastDate: null,
                reasonCounts: [],
                rejects: [],
                sourceArchive: $sourceArchive,
                recordedCount: $recordedCount,
            );
        }

        $firstDate = null;
        $lastDate = null;
        /** @var array<string,int> $reasonCounts */
        $reasonCounts = [];

        foreach ($rejects as $reject) {
            if ($firstDate === null || $reject->gameDate < $firstDate) {
                $firstDate = $reject->gameDate;
            }
            if ($lastDate === null || $reject->gameDate > $lastDate) {
                $lastDate = $reject->gameDate;
            }
            if (!isset($reasonCounts[$reject->reason])) {
                $reasonCounts[$reject->reason] = 0;
            }
            $reasonCounts[$reject->reason]++;
        }

        return new self(
            count: count($rejects),
            firstDate: $firstDate,
            lastDate: $lastDate,
            reasonCounts: $reasonCounts,
            rejects: $rejects,
            sourceArchive: $sourceArchive,
            recordedCount: $recordedCount,
        );
    }

    /**
     * Returns a one-line audit note about how many rejects were persisted.
     * Returns '' when there is nothing meaningful to report (no rejects, or
     * recordedCount was not provided).
     */
    public function auditNote(): string
    {
        if ($this->recordedCount === null) {
            return '';
        }

        if ($this->count === 0) {
            return '';
        }

        if ($this->recordedCount === 0) {
            return sprintf(
                'AUDIT WRITE FAILED: %d reject(s) were NOT persisted to schedule_guard_rejects. The games were still blocked from import; see the audit log for the cause.',
                $this->count,
            );
        }

        if ($this->recordedCount < $this->count) {
            return sprintf(
                'Recorded %d of %d reject(s) to schedule_guard_rejects (cap reached).',
                $this->recordedCount,
                $this->count,
            );
        }

        return sprintf(
            'Recorded %d of %d reject(s) to schedule_guard_rejects.',
            $this->recordedCount,
            $this->count,
        );
    }

    public function isEmpty(): bool
    {
        return $this->count === 0;
    }

    public function headline(): string
    {
        if ($this->count === 0 || $this->firstDate === null || $this->lastDate === null) {
            return '';
        }

        if ($this->firstDate === $this->lastDate) {
            $datePhrase = $this->firstDate;
        } else {
            $datePhrase = $this->firstDate . ' through ' . $this->lastDate;
        }

        $headline = sprintf('%d game(s) rejected: not in ibl_schedule for %s', $this->count, $datePhrase);

        if ($this->sourceArchive !== null) {
            $headline .= sprintf(' (source: %s)', $this->sourceArchive);
        }

        return $headline;
    }

    /** @return list<string> */
    public function triples(int $limit = self::DEFAULT_TRIPLE_LIMIT): array
    {
        return array_map(
            static fn (RejectedGame $r): string => $r->describe(),
            array_slice($this->rejects, 0, $limit),
        );
    }

    public function overflowCount(int $limit = self::DEFAULT_TRIPLE_LIMIT): int
    {
        return max(0, $this->count - $limit);
    }

    /**
     * Returns a plain-text Discord message summarising this reject batch.
     *
     * Returns '' when isEmpty() — callers must not post on a clean run.
     * Uses mb_strlen/mb_substr to avoid splitting multibyte characters at the
     * DISCORD_MAX_CHARS boundary, which would produce invalid UTF-8 and cause
     * json_encode() to return false.
     */
    public function discordMessage(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $lines = [];
        $lines[] = sprintf(
            ':warning: **Boxscore import rejected %d game(s)** — not written to the database.',
            $this->count,
        );

        if ($this->firstDate === $this->lastDate) {
            $lines[] = 'Dates: ' . $this->firstDate;
        } else {
            $lines[] = 'Dates: ' . $this->firstDate . ' through ' . $this->lastDate;
        }

        if ($this->sourceArchive !== null) {
            $lines[] = 'Source: ' . $this->sourceArchive;
        }

        $reasonParts = [];
        foreach ($this->reasonCounts as $reason => $count) {
            $reasonParts[] = $reason . ' ' . $count;
        }
        $lines[] = 'Reasons: ' . implode(', ', $reasonParts);

        $auditNote = $this->auditNote();
        if ($auditNote !== '') {
            $lines[] = $auditNote;
        }

        $lines[] = 'Rejected:';
        foreach ($this->triples(self::DISCORD_TRIPLE_LIMIT) as $triple) {
            $lines[] = '  ' . $triple;
        }

        $overflow = $this->overflowCount(self::DISCORD_TRIPLE_LIMIT);
        if ($overflow > 0) {
            $lines[] = '... and ' . $overflow . ' more. See the updater output for the full list.';
        }

        $message = implode("\n", $lines);

        $suffix = "\n… (truncated)";
        if (mb_strlen($message) > self::DISCORD_MAX_CHARS) {
            $message = mb_substr($message, 0, self::DISCORD_MAX_CHARS - mb_strlen($suffix)) . $suffix;
        }

        return $message;
    }
}
