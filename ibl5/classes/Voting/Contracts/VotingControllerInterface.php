<?php

declare(strict_types=1);

namespace Voting\Contracts;

interface VotingControllerInterface
{
    public function main(mixed $user): void;
    public function submitAsgVote(mixed $user): void;
    public function submitEoyVote(mixed $user): void;
}
