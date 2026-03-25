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
    <p>MVP Choice 1: <?= HtmlSanitizer::e($ballot['MVP_1']) ?></p>
    <p>MVP Choice 2: <?= HtmlSanitizer::e($ballot['MVP_2']) ?></p>
    <p>MVP Choice 3: <?= HtmlSanitizer::e($ballot['MVP_3']) ?></p>

    <p>6th Man Choice 1: <?= HtmlSanitizer::e($ballot['Six_1']) ?></p>
    <p>6th Man Choice 2: <?= HtmlSanitizer::e($ballot['Six_2']) ?></p>
    <p>6th Man Choice 3: <?= HtmlSanitizer::e($ballot['Six_3']) ?></p>

    <p>ROY Choice 1: <?= HtmlSanitizer::e($ballot['ROY_1']) ?></p>
    <p>ROY Choice 2: <?= HtmlSanitizer::e($ballot['ROY_2']) ?></p>
    <p>ROY Choice 3: <?= HtmlSanitizer::e($ballot['ROY_3']) ?></p>

    <p>GM Choice 1: <?= HtmlSanitizer::e($ballot['GM_1']) ?></p>
    <p>GM Choice 2: <?= HtmlSanitizer::e($ballot['GM_2']) ?></p>
    <p>GM Choice 3: <?= HtmlSanitizer::e($ballot['GM_3']) ?></p>

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
            'ecf' => ['label' => 'Eastern Frontcourt', 'fields' => ['East_F1', 'East_F2', 'East_F3', 'East_F4']],
            'ecb' => ['label' => 'Eastern Backcourt', 'fields' => ['East_B1', 'East_B2', 'East_B3', 'East_B4']],
            'wcf' => ['label' => 'Western Frontcourt', 'fields' => ['West_F1', 'West_F2', 'West_F3', 'West_F4']],
            'wcb' => ['label' => 'Western Backcourt', 'fields' => ['West_B1', 'West_B2', 'West_B3', 'West_B4']],
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
