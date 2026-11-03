<?php

declare(strict_types=1);

namespace OnThisDay;

/**
 * One run of moment copy; $url is app-relative with raw `&` (each surface escapes or absolutizes it).
 */
final readonly class MomentSegment
{
    public function __construct(public string $text, public ?string $url = null)
    {
    }
}
