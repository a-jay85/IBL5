<?php

declare(strict_types=1);

/**
 * Voting Module - Display ballot form and handle vote submission
 *
 * Routes:
 *  - default: Show ballot form (ASG during Regular Season, EOY otherwise)
 *  - submit_asg: Process All-Star Game ballot submission
 *  - submit_eoy: Process End-of-Year ballot submission
 *
 * @see Voting\VotingBallotService For ballot data assembly
 * @see Voting\VotingBallotView For ballot form rendering
 * @see Voting\VotingSubmissionService For vote validation and persistence
 * @see Voting\VotingSubmissionView For submission result rendering
 */

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are read here via the Http\HttpRequest value object.
$httpRequest = \Http\HttpRequest::fromGlobals();
$op = is_string($httpRequest->request('op')) ? $httpRequest->request('op') : '';

use Voting\VotingBallotService;
use Voting\VotingBallotView;
use Voting\VotingRepository;
use Voting\VotingSubmissionService;
use Voting\VotingSubmissionView;

global $mysqli_db, $user, $authService;

$repository        = new VotingRepository($mysqli_db);
$ballotService     = new VotingBallotService($mysqli_db);
$ballotView        = new VotingBallotView();
$submissionService = new VotingSubmissionService($repository);
$submissionView    = new VotingSubmissionView();
$nukeCompat        = new \Utilities\NukeCompat();
$teamIdentityRepo  = new \Repositories\TeamIdentityRepository($mysqli_db);
$controller        = new \Voting\VotingController(
    $mysqli_db, $ballotService, $ballotView,
    $submissionService, $submissionView, $nukeCompat, $authService,
    $teamIdentityRepo
);

switch ($op) {
    case 'submit_asg':
        $controller->submitAsgVote($user);
        break;
    case 'submit_eoy':
        $controller->submitEoyVote($user);
        break;
    default:
        $controller->main($user);
        break;
}
