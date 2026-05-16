<?php

declare(strict_types=1);

namespace LastSimRecap\Contracts;

use LastSimRecap\Dto\RecapSlate;

interface LastSimRecapViewInterface
{
    public function render(RecapSlate $slate): string;
}
