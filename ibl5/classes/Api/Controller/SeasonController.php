<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Cache\ETagHandler;
use Api\Contracts\ControllerInterface;
use Api\Response\JsonResponder;

class SeasonController implements ControllerInterface
{
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
        $season = new \Season($this->db);
        $etag = new ETagHandler();

        $phaseSimNumber = $season->getPhaseSpecificSimNumber();

        $data = [
            'phase' => $season->phase,
            'last_sim' => [
                'number' => $season->lastSimNumber,
                'phase_sim_number' => $phaseSimNumber,
                'start_date' => $season->lastSimStartDate,
                'end_date' => $season->lastSimEndDate,
            ],
        ];

        $cacheKey = $season->phase . $season->lastSimNumber . $phaseSimNumber . $season->lastSimStartDate . $season->lastSimEndDate;
        $tag = $etag->generate($cacheKey);
        if ($etag->matches($tag)) {
            $responder->notModified();
            return;
        }

        $responder->success($data, [], 200, $etag->getHeaders($tag));
    }
}
