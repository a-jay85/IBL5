<?php

declare(strict_types=1);

// Interface declarations have no body and must not be flagged as duplicates.
interface ModifierInterface
{
    public function calculateWinnerModifier(): float;
}
