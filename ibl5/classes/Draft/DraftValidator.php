<?php

declare(strict_types=1);

namespace Draft;

use Draft\Contracts\DraftValidatorInterface;

/**
 * @see DraftValidatorInterface
 */
class DraftValidator implements DraftValidatorInterface
{
    private $errors = [];

    /**
     * @see DraftValidatorInterface::validateDraftSelection()
     */
    public function validateDraftSelection(?string $playerName, ?string $currentDraftSelection, bool $isPlayerAlreadyDrafted = false): bool
    {
        $this->clearErrors();
        if ($playerName === null || $playerName === '') {
            $this->errors[] = "You didn't select a player.";
            return false;
        }
        if ($currentDraftSelection !== null && $currentDraftSelection !== '') {
            $this->errors[] = "It looks like you've already drafted a player with this draft pick.";
            return false;
        }
        if ($isPlayerAlreadyDrafted) {
            $this->errors[] = "This player has already been drafted by another team.";
            return false;
        }

        return true;
    }

    /**
     * @see DraftValidatorInterface::getErrors()
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @see DraftValidatorInterface::clearErrors()
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }
}
