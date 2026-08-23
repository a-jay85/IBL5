<?php

declare(strict_types=1);

namespace Boxscore;

final class RejectSummary
{
    public const DEFAULT_TRIPLE_LIMIT = 25;

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
    ) {
    }

    /** @param list<RejectedGame> $rejects */
    public static function fromRejects(array $rejects, ?string $sourceArchive = null): self
    {
        if ($rejects === []) {
            return new self(
                count: 0,
                firstDate: null,
                lastDate: null,
                reasonCounts: [],
                rejects: [],
                sourceArchive: $sourceArchive,
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
}
