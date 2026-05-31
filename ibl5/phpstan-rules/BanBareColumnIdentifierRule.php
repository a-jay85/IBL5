<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans bare (unbackticked) table-qualified column references in SQL — e.g.
 * `ibl_plr.pid` or `ibl_team_info.team_name` written without backticks.
 *
 * This extends BanBareTableIdentifierRule (which covers FROM/JOIN/UPDATE/INTO/DELETE
 * table tokens) into the SELECT/WHERE/ORDER BY/SET column clauses. The target is
 * deliberately narrow: only `ibl_<table>.<column>` qualified references, which are
 * unambiguously identifiers thanks to the `ibl_` prefix. Bare unqualified columns
 * (`name`, `pid`) and alias-qualified columns (`p.name`) are NOT flagged — they
 * cannot be disambiguated from keywords/aliases without a column inventory, so
 * flagging them would produce a false-positive flood.
 *
 * Both the table and column halves should be backticked for rename safety:
 * `` `ibl_plr`.`pid` `` (or reference via a backticked-table alias).
 *
 * @implements Rule<String_>
 */
final class BanBareColumnIdentifierRule implements Rule
{
    // Match ibl_<table>.<column> where the table half is not already inside backticks
    // and the column half is a plain word (so `ibl_plr.*` is excluded — `*` is not a
    // column identifier and cannot be backticked).
    private const PATTERN = '/(?<![`\'"\w.])(ibl_[a-z_]+)\.([a-zA-Z_][a-zA-Z0-9_]*)\b(?!`)/i';

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

        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        if (preg_match_all(self::PATTERN, $node->value, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        $errors = [];

        foreach ($matches as $m) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                "Table-qualified column '%s.%s' must be wrapped in backticks (`%s`.`%s`) for "
                . 'PHPStan rename safety.',
                $m[1],
                $m[2],
                $m[1],
                $m[2],
            ))
                ->identifier('ibl.bareColumnIdentifier')
                ->build();
        }

        return $errors;
    }
}
