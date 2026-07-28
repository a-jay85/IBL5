<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Repository\ApiPlayerRepository;
use Api\Response\CsvResponder;
use Api\Response\JsonResponder;
use Api\Transformer\PlayerExportTransformer;

class PlayerExportController implements ControllerInterface
{
    private ApiPlayerRepository $repo;

    public function __construct(ApiPlayerRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
        $repo = $this->repo;
        $transformer = new PlayerExportTransformer();
        $csv = new CsvResponder();

        $rows = $repo->getAllPlayersForExport();

        $output = [$transformer->getHeaders()];
        foreach ($rows as $row) {
            $output[] = $transformer->transform($row);
        }

        $csv->send($output, 'ibl-players.csv');
    }
}
