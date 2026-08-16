<?php

declare(strict_types=1);

namespace Voting;

use Voting\Contracts\VotingControllerInterface;

class VotingController implements VotingControllerInterface
{
    public function __construct(
        private \mysqli $db,
        private \Voting\Contracts\VotingBallotServiceInterface $ballotService,
        private \Voting\Contracts\VotingBallotViewInterface $ballotView,
        private \Voting\Contracts\VotingSubmissionServiceInterface $submissionService,
        private \Voting\Contracts\VotingSubmissionViewInterface $submissionView,
        private \Utilities\NukeCompat $nukeCompat,
        private \Auth\Contracts\AuthServiceInterface $authService,
        private \Repositories\Contracts\TeamIdentityRepositoryInterface $teamIdentityRepository,
    ) {}

    public function main(mixed $user): void
    {
        if (!$this->nukeCompat->isUser($user)) {
            $this->nukeCompat->loginBox();
            return;
        }
        $this->nukeCompat->cookieDecode($user);
        $this->showBallot($this->authService->getUsername() ?? '');
    }

    public function submitAsgVote(mixed $user): void
    {
        \PageLayout\PageLayout::header();

        $responder = new \Api\Response\HtmlResponder();

        if (!\Security\CsrfGuard::validateSubmittedToken('asg_vote')) {
            // Not "go back": the token is single-use, so the Back page carries the
            // dead one and a resubmit lands right back here. Only a fresh ballot
            // (fresh token) recovers. See
            // ibl5/docs/backlog/voting-csrf-single-use-post-redisplay.md
            $responder->html('Invalid or expired form submission. <a href="modules.php?name=Voting">Return to the ballot</a> to start over with a fresh form.');
            \PageLayout\PageLayout::footer();
            return;
        }

        // POST submission — terse error is appropriate, not loginbox()
        if (!$this->nukeCompat->isUser($user)) {
            $responder->html('You must be logged in to vote.');
            \PageLayout\PageLayout::footer();
            return;
        }
        $this->nukeCompat->cookieDecode($user);
        $username = $this->authService->getUsername() ?? '';
        $commonRepository = $this->teamIdentityRepository;
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

        $result = $this->submissionService->submitAsgVote($teamName, $ballot, $rawPostCategories);

        if ($result->hasErrors()) {
            $responder->html($this->submissionView->renderErrors($result->errors));
        } else {
            \EventLog\EventLogger::setAction('asg_vote_submitted');
            $responder->html($this->submissionView->renderAsgConfirmation($teamName, $ballot));
        }

        \PageLayout\PageLayout::footer();
    }

    public function submitEoyVote(mixed $user): void
    {
        \PageLayout\PageLayout::header();

        $responder = new \Api\Response\HtmlResponder();

        if (!\Security\CsrfGuard::validateSubmittedToken('eoy_vote')) {
            // Not "go back": the token is single-use, so the Back page carries the
            // dead one and a resubmit lands right back here. Only a fresh ballot
            // (fresh token) recovers. See
            // ibl5/docs/backlog/voting-csrf-single-use-post-redisplay.md
            $responder->html('Invalid or expired form submission. <a href="modules.php?name=Voting">Return to the ballot</a> to start over with a fresh form.');
            \PageLayout\PageLayout::footer();
            return;
        }

        // POST submission — terse error is appropriate, not loginbox()
        if (!$this->nukeCompat->isUser($user)) {
            $responder->html('You must be logged in to vote.');
            \PageLayout\PageLayout::footer();
            return;
        }
        $this->nukeCompat->cookieDecode($user);
        $username = $this->authService->getUsername() ?? '';
        $commonRepository = $this->teamIdentityRepository;
        $teamName = $commonRepository->getTeamnameFromUsername($username) ?? '';

        $mvp = is_array($_POST['MVP'] ?? null) ? $_POST['MVP'] : [];
        $six = is_array($_POST['Six'] ?? null) ? $_POST['Six'] : [];
        $roy = is_array($_POST['ROY'] ?? null) ? $_POST['ROY'] : [];
        $gm  = is_array($_POST['GM']  ?? null) ? $_POST['GM']  : [];

        /** @var array{mvp_1: string, mvp_2: string, mvp_3: string, six_1: string, six_2: string, six_3: string, roy_1: string, roy_2: string, roy_3: string, gm_1: string, gm_2: string, gm_3: string} $ballot */
        $ballot = [
            'mvp_1' => is_string($mvp[1] ?? null) ? $mvp[1] : '',
            'mvp_2' => is_string($mvp[2] ?? null) ? $mvp[2] : '',
            'mvp_3' => is_string($mvp[3] ?? null) ? $mvp[3] : '',
            'six_1' => is_string($six[1] ?? null) ? $six[1] : '',
            'six_2' => is_string($six[2] ?? null) ? $six[2] : '',
            'six_3' => is_string($six[3] ?? null) ? $six[3] : '',
            'roy_1' => is_string($roy[1] ?? null) ? $roy[1] : '',
            'roy_2' => is_string($roy[2] ?? null) ? $roy[2] : '',
            'roy_3' => is_string($roy[3] ?? null) ? $roy[3] : '',
            'gm_1'  => is_string($gm[1]  ?? null) ? $gm[1]  : '',
            'gm_2'  => is_string($gm[2]  ?? null) ? $gm[2]  : '',
            'gm_3'  => is_string($gm[3]  ?? null) ? $gm[3]  : '',
        ];

        $result = $this->submissionService->submitEoyVote($teamName, $ballot);

        if ($result->hasErrors()) {
            $responder->html($this->submissionView->renderErrors($result->errors));
        } else {
            \EventLog\EventLogger::setAction('eoy_vote_submitted');
            $responder->html($this->submissionView->renderEoyConfirmation($teamName, $ballot));
        }

        \PageLayout\PageLayout::footer();
    }

    private function showBallot(string $username): void
    {
        $commonRepository = $this->teamIdentityRepository;
        $season = new \Season\Season($this->db);
        $league = new \League\League($this->db);

        $voterTeamName = $commonRepository->getTeamnameFromUsername($username) ?? '';
        $teamid = $commonRepository->getTidFromTeamname($voterTeamName) ?? 0;

        $formAction = ($season->phase === 'Regular Season')
            ? 'modules.php?name=Voting&op=submit_asg'
            : 'modules.php?name=Voting&op=submit_eoy';

        $categories = $this->ballotService->getBallotData($voterTeamName, $season, $league);

        \PageLayout\PageLayout::header();

        $responder = new \Api\Response\HtmlResponder();
        $responder->html(($season->phase === 'Regular Season')
            ? '<h1 class="ibl-title">All-Star Game Ballot</h1>'
            : '<h1 class="ibl-title">End-of-Year Awards Ballot</h1>');
        $responder->html($this->ballotView->renderBallotForm($formAction, $voterTeamName, $teamid, $season->phase, $categories));
        \PageLayout\PageLayout::footer();
    }
}
