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

/** @var array{East_F1: string, East_F2: string, East_F3: string, East_F4: string, East_B1: string, East_B2: string, East_B3: string, East_B4: string, West_F1: string, West_F2: string, West_F3: string, West_F4: string, West_B1: string, West_B2: string, West_B3: string, West_B4: string} $ballot */
$ballot = [
    'East_F1' => is_string($ecf[0] ?? null) ? $ecf[0] : '',
    'East_F2' => is_string($ecf[1] ?? null) ? $ecf[1] : '',
    'East_F3' => is_string($ecf[2] ?? null) ? $ecf[2] : '',
    'East_F4' => is_string($ecf[3] ?? null) ? $ecf[3] : '',
    'East_B1' => is_string($ecb[0] ?? null) ? $ecb[0] : '',
    'East_B2' => is_string($ecb[1] ?? null) ? $ecb[1] : '',
    'East_B3' => is_string($ecb[2] ?? null) ? $ecb[2] : '',
    'East_B4' => is_string($ecb[3] ?? null) ? $ecb[3] : '',
    'West_F1' => is_string($wcf[0] ?? null) ? $wcf[0] : '',
    'West_F2' => is_string($wcf[1] ?? null) ? $wcf[1] : '',
    'West_F3' => is_string($wcf[2] ?? null) ? $wcf[2] : '',
    'West_F4' => is_string($wcf[3] ?? null) ? $wcf[3] : '',
    'West_B1' => is_string($wcb[0] ?? null) ? $wcb[0] : '',
    'West_B2' => is_string($wcb[1] ?? null) ? $wcb[1] : '',
    'West_B3' => is_string($wcb[2] ?? null) ? $wcb[2] : '',
    'West_B4' => is_string($wcb[3] ?? null) ? $wcb[3] : '',
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
