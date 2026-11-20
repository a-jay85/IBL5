<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning;

use League\League;

/**
 * The tuning page's two GET parameters, parsed against closed sets.
 *
 * Each parameter fails closed to its default on its own: a refused value is
 * replaced by the default and its GET name is listed in `ignored`. No other
 * request value is read anywhere on the page.
 */
final readonly class TuningFilter
{
    /** One team by default; `team=all` is the explicit opt-in for every franchise. */
    public const int DEFAULT_TID = 1;

    /**
     * @param ?int $tid null = every real franchise (only on `team=all`)
     * @param list<string> $ignored GET names whose value was refused
     */
    public function __construct(
        public ?int $tid,
        public TuningStatus $status,
        public array $ignored,
    ) {
    }

    /**
     * @param array<array-key, mixed> $get
     */
    public static function fromQuery(array $get): self
    {
        $ignored = [];

        $tid = self::DEFAULT_TID;
        $rawTeam = $get['team'] ?? null;
        if ($rawTeam === 'all') {
            $tid = null;
        } elseif ($rawTeam !== null && $rawTeam !== '') {
            if (is_string($rawTeam) && preg_match('/^[1-9][0-9]?$/', $rawTeam) === 1 && League::isRealFranchise((int) $rawTeam)) {
                $tid = (int) $rawTeam;
            } else {
                $ignored[] = 'team';
            }
        }

        $status = TuningStatus::All;
        $rawStatus = $get['status'] ?? null;
        if ($rawStatus !== null && $rawStatus !== '') {
            $parsed = is_string($rawStatus) ? TuningStatus::tryFrom($rawStatus) : null;
            if ($parsed !== null) {
                $status = $parsed;
            } else {
                $ignored[] = 'status';
            }
        }

        return new self($tid, $status, $ignored);
    }

    /**
     * The query rebuilt from the typed fields, never from the raw request.
     *
     * @return array{team: string, status: string}
     */
    public function toQuery(): array
    {
        return [
            'team' => $this->tid === null ? 'all' : (string) $this->tid,
            'status' => $this->status->value,
        ];
    }
}
