<?php

declare(strict_types=1);

/**
 * End-of-Year Vote Submission Handler
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

if (!\Utilities\CsrfGuard::validateSubmittedToken('eoy_vote')) {
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

PageLayout\PageLayout::footer();
