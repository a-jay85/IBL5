<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans backtick-quoted references to former reserved-word / meaning-flipped
 * column names (migration 113). Prevents the columns from being re-introduced
 * by future PRs that would otherwise silently re-require `snap.`to`` or hit
 * the `r_to` semantic-flip in ibl_hist.
 *
 * Rationale per migration 113: `to`/`do` were renamed to `r_trans_off`/
 * `r_drive_off`, `r_to` (live/snapshot turnover rating) renamed to `r_tvr`,
 * and `Start Date`/`End Date` renamed to `start_date`/`end_date`.
 *
 * @implements Rule<String_>
 */
final class BanReservedWordColumnsRule implements Rule
{
    /**
     * Backticked identifiers banned in SQL string literals. Keyed by the
     * exact backtick-quoted token; value is a short explanation.
     */
    private const BANNED_TOKENS = [
        '`to`' => 'Rename to `r_trans_off` (transition offense rating); the bare `to` column was a SQL reserved word.',
        '`do`' => 'Rename to `r_drive_off` (drive offense rating); the bare `do` column was a SQL reserved word.',
        '`r_to`' => 'Rename to `r_tvr` (live/snapshot turnover rating) or `r_trans_off` (hist transition offense rating) — `r_to` used to flip meaning across layers.',
        '`Start Date`' => 'Rename to `start_date` — space-containing identifier banned.',
        '`End Date`' => 'Rename to `end_date` — space-containing identifier banned.',
        '`key`' => 'Rename to `cache_key` on the `cache` / `cache_locks` tables; the bare `key` column is a SQL reserved word (migration 116).',
    ];

    public function getNodeType(): string
    {
        return String_::class;
    }

    /**
     * @param String_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        // Enforce in classes/ and html/ only — tests, migrations, and scripts
        // may still reference old names in comments or historical fixtures.
        $isClassesFile = str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR);
        $isHtmlFile = str_contains($file, DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR);

        if (!$isClassesFile && !$isHtmlFile) {
            return [];
        }

        $value = $node->value;
        $errors = [];

        foreach (self::BANNED_TOKENS as $token => $guidance) {
            if (str_contains($value, $token)) {
                $errors[] = RuleErrorBuilder::message(
                    'Banned backtick-quoted column reference ' . $token . ' in SQL string. '
                    . $guidance
                )
                    ->identifier('ibl.bannedReservedWordColumn')
                    ->build();
            }
        }

        return $errors;
    }
}
