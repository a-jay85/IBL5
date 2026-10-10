<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;
use Voting\VotingBallotService;
use Voting\VotingBallotView;
use Voting\VotingRepository;
use Voting\VotingResultsController;
use Voting\VotingResultsService;
use Voting\VotingResultsView;
use Voting\VotingSubmissionService;
use Voting\VotingSubmissionView;

/**
 * Composition root for modules/Voting/index.php.
 */
final class VotingFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function controller(): \Voting\VotingController
    {
        $db = $this->services->db();
        $repository = new VotingRepository($db);
        $ballotService = new VotingBallotService($db);
        $ballotView = new VotingBallotView();
        $submissionService = new VotingSubmissionService($repository);
        $submissionView = new VotingSubmissionView();
        $nukeCompat = $this->services->nukeCompat();
        $teamIdentityRepo = $this->services->teamIdentity();
        // Built unconditionally; VotingController only calls render() for admins, so the
        // results query never runs for anyone else. Keeping the gate in one place means deleting
        // it fails a unit test instead of being masked by a second check here.
        $resultsController = new VotingResultsController(
            new VotingResultsService($repository),
            new VotingResultsView(),
            $this->services->season()
        );

        return new \Voting\VotingController(
            $db,
            $ballotService,
            $ballotView,
            $submissionService,
            $submissionView,
            $nukeCompat,
            $this->services->auth(),
            $teamIdentityRepo,
            $resultsController
        );
    }
}
