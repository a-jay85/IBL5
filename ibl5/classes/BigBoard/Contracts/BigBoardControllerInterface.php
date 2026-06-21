<?php

declare(strict_types=1);

namespace BigBoard\Contracts;

/**
 * BigBoardControllerInterface - Front controller for the BigBoard module.
 *
 * Serves both the Big Board page (op default) and the Mock Draft page
 * (op=mock), and handles the CSRF-guarded, PRG-redirected POST mutations
 * (op ∈ {add, setrank, setnote, remove}).
 */
interface BigBoardControllerInterface
{
    /**
     * @param mixed $user Current Nuke user payload
     */
    public function handleRequest($user, string $op): void;
}
