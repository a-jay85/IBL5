<?php

declare(strict_types=1);

namespace Draft\Contracts;

use Draft\Dto\DraftBoardData;

interface DraftServiceInterface
{
    /** @see \Draft\DraftService::getDraftBoardData() */
    public function getDraftBoardData(string $username): DraftBoardData;
}
