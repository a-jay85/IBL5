<?php

declare(strict_types=1);

namespace BigBoard\Contracts;

/**
 * BigBoardServiceInterface - Owner-scoped orchestration for the GM big board.
 *
 * Every method takes the session $username and resolves the owning teamid
 * SERVER-SIDE (never from the request). All repository writes are scoped to
 * that resolved teamid, so a GM can only ever mutate their own board.
 *
 * @phpstan-import-type BigBoardRow from BigBoardRepositoryInterface
 * @phpstan-import-type AddableProspect from BigBoardRepositoryInterface
 * @phpstan-import-type MockResultRow from MockDraftServiceInterface
 */
interface BigBoardServiceInterface
{
    /**
     * Resolve the franchise teamid owned by $username, or null when the account
     * owns no team (Free Agents / unknown).
     */
    public function resolveOwnerTeamid(string $username): ?int;

    /**
     * The GM's ranked board (empty when no team).
     *
     * @return list<BigBoardRow>
     */
    public function getBoardView(string $username): array;

    /**
     * Undrafted prospects not yet on the GM's board (empty when no team).
     *
     * @return list<AddableProspect>
     */
    public function getAddableProspects(string $username): array;

    /**
     * The GM's mock draft for the given season (empty when no team).
     *
     * @return list<MockResultRow>
     */
    public function getMockDraft(string $username, int $seasonYear): array;

    /**
     * @return array{success: bool, result?: string, error?: string}
     */
    public function addEntry(string $username, int $prospectId, int $rank, string $note): array;

    /**
     * @return array{success: bool, result?: string, error?: string}
     */
    public function setRank(string $username, int $entryId, int $rank): array;

    /**
     * @return array{success: bool, result?: string, error?: string}
     */
    public function setNote(string $username, int $entryId, string $note): array;

    /**
     * @return array{success: bool, result?: string, error?: string}
     */
    public function removeEntry(string $username, int $entryId): array;
}
