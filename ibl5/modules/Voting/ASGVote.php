<?php

declare(strict_types=1);

/**
 * All-Star Vote Submission Handler
 *
 * Thin wrapper: CSRF check → parse POST → validate + save via service → render result via view.
 *
 * @see \Voting\VotingSubmissionService For validation and persistence
 * @see \Voting\VotingSubmissionView For HTML rendering
 */

require __DIR__ . '/../../mainfile.php';

use Voting\VotingRepository;
use Voting\VotingSubmissionService;
use Voting\VotingSubmissionView;

PageLayout\PageLayout::header();

if (!\Utilities\CsrfGuard::validateSubmittedToken('asg_vote')) {
    echo 'Invalid or expired form submission. Please go back and try again.';
    PageLayout\PageLayout::footer();
    return;
}

// Derive team identity from authenticated session, not POST (prevents spoofing)
global $user, $cookie;
if (!is_user($user)) {
    echo 'You must be logged in to vote.';
    PageLayout\PageLayout::footer();
    return;
}
cookiedecode($user);
$username = (string) ($cookie[1] ?? '');
$commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
$teamName = $commonRepository->getTeamnameFromUsername($username) ?? '';

// Parse checkbox arrays (0-indexed from HTML) into typed ballot (1-indexed field names)
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

// Raw post arrays for too-many-votes validation (count check)
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

PageLayout\PageLayout::footer();
