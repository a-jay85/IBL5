<?php

declare(strict_types=1);

namespace Standings\Contracts;

interface OlympicsStandingsViewInterface
{
    public function render(): string;
}
