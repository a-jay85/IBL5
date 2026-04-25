<?php

declare(strict_types=1);

namespace Voting;

use Utilities\HtmlSanitizer;
use Voting\Contracts\VotingRepositoryInterface;
use Voting\Contracts\VotingSubmissionViewInterface;

/**
 * VotingSubmissionView — Renders vote submission confirmation and error pages
 *
 * @phpstan-import-type EoyBallot from VotingRepositoryInterface
 * @phpstan-import-type AsgBallot from VotingRepositoryInterface
 *
 * @see VotingSubmissionViewInterface
 */
class VotingSubmissionView implements VotingSubmissionViewInterface
{
    /**
     * @see VotingSubmissionViewInterface::renderErrors()
     *
     * @param list<string> $errors
     */
    public function renderErrors(array $errors): string
    {
        ob_start();
        foreach ($errors as $error) {
            ?>
<p class="voting-submission-error"><?= HtmlSanitizer::e($error) ?></p>
<?php
        }
        return (string) ob_get_clean();
    }

    /**
     * @see VotingSubmissionViewInterface::renderEoyConfirmation()
     *
     * @param EoyBallot $ballot
     */
    public function renderEoyConfirmation(string $teamName, array $ballot): string
    {
        ob_start();
        ?>
<div class="voting-submission-confirmation">
    <p>MVP Choice 1: <?= HtmlSanitizer::e($ballot['mvp_1']) ?></p>
    <p>MVP Choice 2: <?= HtmlSanitizer::e($ballot['mvp_2']) ?></p>
    <p>MVP Choice 3: <?= HtmlSanitizer::e($ballot['mvp_3']) ?></p>

    <p>6th Man Choice 1: <?= HtmlSanitizer::e($ballot['six_1']) ?></p>
    <p>6th Man Choice 2: <?= HtmlSanitizer::e($ballot['six_2']) ?></p>
    <p>6th Man Choice 3: <?= HtmlSanitizer::e($ballot['six_3']) ?></p>

    <p>ROY Choice 1: <?= HtmlSanitizer::e($ballot['roy_1']) ?></p>
    <p>ROY Choice 2: <?= HtmlSanitizer::e($ballot['roy_2']) ?></p>
    <p>ROY Choice 3: <?= HtmlSanitizer::e($ballot['roy_3']) ?></p>

    <p>GM Choice 1: <?= HtmlSanitizer::e($ballot['gm_1']) ?></p>
    <p>GM Choice 2: <?= HtmlSanitizer::e($ballot['gm_2']) ?></p>
    <p>GM Choice 3: <?= HtmlSanitizer::e($ballot['gm_3']) ?></p>

    <p class="voting-submission-success"><strong>Thank you for voting - the <?= HtmlSanitizer::e($teamName) ?> vote has been recorded!</strong></p>
</div>
<?php
        return (string) ob_get_clean();
    }

    /**
     * @see VotingSubmissionViewInterface::renderAsgConfirmation()
     *
     * @param AsgBallot $ballot
     */
    public function renderAsgConfirmation(string $teamName, array $ballot): string
    {
        /** @var array<string, array{label: string, fields: list<string>}> */
        $categories = [
            'ecf' => ['label' => 'Eastern Frontcourt', 'fields' => ['east_f1', 'east_f2', 'east_f3', 'east_f4']],
            'ecb' => ['label' => 'Eastern Backcourt', 'fields' => ['east_b1', 'east_b2', 'east_b3', 'east_b4']],
            'wcf' => ['label' => 'Western Frontcourt', 'fields' => ['west_f1', 'west_f2', 'west_f3', 'west_f4']],
            'wcb' => ['label' => 'Western Backcourt', 'fields' => ['west_b1', 'west_b2', 'west_b3', 'west_b4']],
        ];

        ob_start();
        ?>
<div class="voting-submission-confirmation">
<?php foreach ($categories as $cat): ?>
<?php foreach ($cat['fields'] as $field): ?>
    <p><?= HtmlSanitizer::e($cat['label']) ?>: <?= HtmlSanitizer::e($ballot[$field]) ?></p>
<?php endforeach; ?>

<?php endforeach; ?>
    <p class="voting-submission-success"><strong>Thank you for voting - the <?= HtmlSanitizer::e($teamName) ?> vote has been recorded!</strong></p>
</div>
<?php
        return (string) ob_get_clean();
    }
}
