<?php

declare(strict_types=1);

namespace Player\CareerArc;

/**
 * A marker on the career arc timeline. The text is plain and unescaped; the view escapes it.
 */
final readonly class ArcEvent
{
    public const DRAFT = 'draft';
    public const TRADE = 'trade';
    public const WAIVER = 'waiver';
    public const AWARD = 'award';
    public const RETIRED = 'retired';

    public function __construct(
        public string $type,
        public int $year,
        public string $text,
    ) {
    }
}
