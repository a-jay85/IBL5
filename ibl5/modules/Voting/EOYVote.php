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

/** @var array{MVP_1: string, MVP_2: string, MVP_3: string, Six_1: string, Six_2: string, Six_3: string, ROY_1: string, ROY_2: string, ROY_3: string, GM_1: string, GM_2: string, GM_3: string} $ballot */
$ballot = [
    'MVP_1' => is_string($_POST['MVP'][1] ?? null) ? $_POST['MVP'][1] : '',
    'MVP_2' => is_string($_POST['MVP'][2] ?? null) ? $_POST['MVP'][2] : '',
    'MVP_3' => is_string($_POST['MVP'][3] ?? null) ? $_POST['MVP'][3] : '',
    'Six_1' => is_string($_POST['Six'][1] ?? null) ? $_POST['Six'][1] : '',
    'Six_2' => is_string($_POST['Six'][2] ?? null) ? $_POST['Six'][2] : '',
    'Six_3' => is_string($_POST['Six'][3] ?? null) ? $_POST['Six'][3] : '',
    'ROY_1' => is_string($_POST['ROY'][1] ?? null) ? $_POST['ROY'][1] : '',
    'ROY_2' => is_string($_POST['ROY'][2] ?? null) ? $_POST['ROY'][2] : '',
    'ROY_3' => is_string($_POST['ROY'][3] ?? null) ? $_POST['ROY'][3] : '',
    'GM_1'  => is_string($_POST['GM'][1] ?? null)  ? $_POST['GM'][1]  : '',
    'GM_2'  => is_string($_POST['GM'][2] ?? null)  ? $_POST['GM'][2]  : '',
    'GM_3'  => is_string($_POST['GM'][3] ?? null)  ? $_POST['GM'][3]  : '',
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
