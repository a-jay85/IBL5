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
// inputs are now read from $_REQUEST explicitly here.
$op = is_string($_REQUEST['op'] ?? null) ? $_REQUEST['op'] : '';

use Voting\VotingBallotService;
use Voting\VotingBallotView;
use Voting\VotingRepository;
use Voting\VotingSubmissionService;
use Voting\VotingSubmissionView;

/**
 * Display the ballot form for an authenticated user
 */
function userinfo(string $username): void
{
    global $mysqli_db;

    $commonRepository = new \Repositories\TeamIdentityRepository($mysqli_db);
    $season = new \Season\Season($mysqli_db);
    $league = new \League\League($mysqli_db);

    $voterTeamName = $commonRepository->getTeamnameFromUsername($username) ?? '';
    $teamid = $commonRepository->getTidFromTeamname($voterTeamName) ?? 0;

    $formAction = ($season->phase === 'Regular Season')
        ? 'modules.php?name=Voting&op=submit_asg'
        : 'modules.php?name=Voting&op=submit_eoy';

    // Assemble ballot data and render
    $service = new VotingBallotService($mysqli_db);
    $view = new VotingBallotView();

    $categories = $service->getBallotData($voterTeamName, $season, $league);

    PageLayout\PageLayout::header();
    echo ($season->phase === 'Regular Season')
        ? '<h1 class="ibl-title">All-Star Game Ballot</h1>'
        : '<h1 class="ibl-title">End-of-Year Awards Ballot</h1>';
    echo $view->renderBallotForm($formAction, $voterTeamName, $teamid, $season->phase, $categories);
    PageLayout\PageLayout::footer();
}

/**
 * Entry point — check authentication before showing ballot
 *
 * @param mixed $user User authentication data
 */
function main(mixed $user): void
{
    if (!is_user($user)) {
        loginbox();
    } else {
        global $cookie;
        cookiedecode($user);
        userinfo((string)($cookie[1] ?? ''));
    }
}

function submitAsgVote(mixed $user): void
{
    global $mysqli_db, $cookie;

    \PageLayout\PageLayout::header();

    if (!\Security\CsrfGuard::validateSubmittedToken('asg_vote')) {
        echo 'Invalid or expired form submission. Please go back and try again.';
        \PageLayout\PageLayout::footer();
        return;
    }

    // POST submission — terse error is appropriate, not loginbox()
    if (!is_user($user)) {
        echo 'You must be logged in to vote.';
        \PageLayout\PageLayout::footer();
        return;
    }
    cookiedecode($user);
    $username = (string) ($cookie[1] ?? '');
    $commonRepository = new \Repositories\TeamIdentityRepository($mysqli_db);
    $teamName = $commonRepository->getTeamnameFromUsername($username) ?? '';

    $ecf = is_array($_POST['ECF'] ?? null) ? $_POST['ECF'] : [];
    $ecb = is_array($_POST['ECB'] ?? null) ? $_POST['ECB'] : [];
    $wcf = is_array($_POST['WCF'] ?? null) ? $_POST['WCF'] : [];
    $wcb = is_array($_POST['WCB'] ?? null) ? $_POST['WCB'] : [];

    /** @var array{east_f1: string, east_f2: string, east_f3: string, east_f4: string, east_b1: string, east_b2: string, east_b3: string, east_b4: string, west_f1: string, west_f2: string, west_f3: string, west_f4: string, west_b1: string, west_b2: string, west_b3: string, west_b4: string} $ballot */
    $ballot = [
        'east_f1' => is_string($ecf[0] ?? null) ? $ecf[0] : '',
        'east_f2' => is_string($ecf[1] ?? null) ? $ecf[1] : '',
        'east_f3' => is_string($ecf[2] ?? null) ? $ecf[2] : '',
        'east_f4' => is_string($ecf[3] ?? null) ? $ecf[3] : '',
        'east_b1' => is_string($ecb[0] ?? null) ? $ecb[0] : '',
        'east_b2' => is_string($ecb[1] ?? null) ? $ecb[1] : '',
        'east_b3' => is_string($ecb[2] ?? null) ? $ecb[2] : '',
        'east_b4' => is_string($ecb[3] ?? null) ? $ecb[3] : '',
        'west_f1' => is_string($wcf[0] ?? null) ? $wcf[0] : '',
        'west_f2' => is_string($wcf[1] ?? null) ? $wcf[1] : '',
        'west_f3' => is_string($wcf[2] ?? null) ? $wcf[2] : '',
        'west_f4' => is_string($wcf[3] ?? null) ? $wcf[3] : '',
        'west_b1' => is_string($wcb[0] ?? null) ? $wcb[0] : '',
        'west_b2' => is_string($wcb[1] ?? null) ? $wcb[1] : '',
        'west_b3' => is_string($wcb[2] ?? null) ? $wcb[2] : '',
        'west_b4' => is_string($wcb[3] ?? null) ? $wcb[3] : '',
    ];

    /** @var array<string, list<string>> $rawPostCategories */
    $rawPostCategories = [
        'ECF' => array_values(array_filter($ecf, 'is_string')),
        'ECB' => array_values(array_filter($ecb, 'is_string')),
        'WCF' => array_values(array_filter($wcf, 'is_string')),
        'WCB' => array_values(array_filter($wcb, 'is_string')),
    ];

    $repository = new VotingRepository($mysqli_db);
    $service = new VotingSubmissionService($repository);
    $view = new VotingSubmissionView();

    $result = $service->submitAsgVote($teamName, $ballot, $rawPostCategories);

    if ($result->hasErrors()) {
        echo $view->renderErrors($result->errors);
    } else {
        echo $view->renderAsgConfirmation($teamName, $ballot);
    }

    \PageLayout\PageLayout::footer();
}

function submitEoyVote(mixed $user): void
{
    global $mysqli_db, $cookie;

    \PageLayout\PageLayout::header();

    if (!\Security\CsrfGuard::validateSubmittedToken('eoy_vote')) {
        echo 'Invalid or expired form submission. Please go back and try again.';
        \PageLayout\PageLayout::footer();
        return;
    }

    // POST submission — terse error is appropriate, not loginbox()
    if (!is_user($user)) {
        echo 'You must be logged in to vote.';
        \PageLayout\PageLayout::footer();
        return;
    }
    cookiedecode($user);
    $username = (string) ($cookie[1] ?? '');
    $commonRepository = new \Repositories\TeamIdentityRepository($mysqli_db);
    $teamName = $commonRepository->getTeamnameFromUsername($username) ?? '';

    /** @var array{mvp_1: string, mvp_2: string, mvp_3: string, six_1: string, six_2: string, six_3: string, roy_1: string, roy_2: string, roy_3: string, gm_1: string, gm_2: string, gm_3: string} $ballot */
    $ballot = [
        'mvp_1' => is_string($_POST['MVP'][1] ?? null) ? $_POST['MVP'][1] : '',
        'mvp_2' => is_string($_POST['MVP'][2] ?? null) ? $_POST['MVP'][2] : '',
        'mvp_3' => is_string($_POST['MVP'][3] ?? null) ? $_POST['MVP'][3] : '',
        'six_1' => is_string($_POST['Six'][1] ?? null) ? $_POST['Six'][1] : '',
        'six_2' => is_string($_POST['Six'][2] ?? null) ? $_POST['Six'][2] : '',
        'six_3' => is_string($_POST['Six'][3] ?? null) ? $_POST['Six'][3] : '',
        'roy_1' => is_string($_POST['ROY'][1] ?? null) ? $_POST['ROY'][1] : '',
        'roy_2' => is_string($_POST['ROY'][2] ?? null) ? $_POST['ROY'][2] : '',
        'roy_3' => is_string($_POST['ROY'][3] ?? null) ? $_POST['ROY'][3] : '',
        'gm_1'  => is_string($_POST['GM'][1] ?? null)  ? $_POST['GM'][1]  : '',
        'gm_2'  => is_string($_POST['GM'][2] ?? null)  ? $_POST['GM'][2]  : '',
        'gm_3'  => is_string($_POST['GM'][3] ?? null)  ? $_POST['GM'][3]  : '',
    ];

    $repository = new VotingRepository($mysqli_db);
    $service = new VotingSubmissionService($repository);
    $view = new VotingSubmissionView();

    $result = $service->submitEoyVote($teamName, $ballot);

    if ($result->hasErrors()) {
        echo $view->renderErrors($result->errors);
    } else {
        echo $view->renderEoyConfirmation($teamName, $ballot);
    }

    \PageLayout\PageLayout::footer();
}

switch ($op) {
    case 'submit_asg':
        submitAsgVote($user);
        break;
    case 'submit_eoy':
        submitEoyVote($user);
        break;
    default:
        main($user);
        break;
}
