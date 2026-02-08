<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Response\JsonResponder;

class GameListController implements ControllerInterface
{
    /** @phpstan-ignore property.onlyWritten */
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder): void
    {
        $responder->error(501, 'not_implemented', 'This endpoint is not yet implemented.');
    }
}
