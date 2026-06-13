<?php

declare(strict_types=1);

namespace BigBoard\Contracts;

/**
 * BigBoardViewInterface - HTML rendering for the GM big board + mock draft.
 *
 * Every dynamic value is escaped via \Security\HtmlSanitizer::e(). One shared
 * raw CSRF token is passed in per render and embedded in every form (a board of
 * many rows would otherwise blow past CsrfGuard::MAX_TOKENS).
 *
 * @phpstan-import-type BigBoardRow from BigBoardRepositoryInterface
 * @phpstan-import-type AddableProspect from BigBoardRepositoryInterface
 * @phpstan-import-type MockResultRow from MockDraftServiceInterface
 */
interface BigBoardViewInterface
{
    /**
     * Render the Big Board page (ranked list + add form).
     *
     * @param list<BigBoardRow> $rows
     * @param list<AddableProspect> $addable
     */
    public function renderBigBoardPage(
        array $rows,
        array $addable,
        ?string $result,
        ?string $error,
        string $rawToken,
        bool $hasTeam = true
    ): string;

    /**
     * Render the Mock Draft page (the GM's owned picks + suggestions).
     *
     * @param list<MockResultRow> $picks
     */
    public function renderMockDraftPage(array $picks, bool $hasTeam = true): string;
}
