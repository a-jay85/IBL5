<?php

declare(strict_types=1);

namespace TradeSuggestions\Feedback;

/**
 * Why a GM hid a trade idea. The closed set the hide form offers.
 */
enum HideReason: string
{
    case TooOld = 'TOO_OLD';
    case TooExpensive = 'TOO_EXPENSIVE';
    case WeakensTeam = 'WEAKENS_TEAM';
    case WrongPosition = 'WRONG_POSITION';
    case NotRealistic = 'NOT_REALISTIC';
    case Other = 'OTHER';

    /** Length of all six values joined by commas. */
    public const int MAX_JOINED_LENGTH = 69;
    /** Width of `ibl_trade_suggestion_feedback.reason_codes`. */
    public const int COLUMN_WIDTH = 80;

    /**
     * Parse submitted codes. Returns the reasons in declaration order without
     * repeats, or null when the input is not a non-empty list of known codes.
     * One unknown code rejects the whole set.
     *
     * @return list<self>|null
     */
    public static function fromCodes(mixed $raw): ?array
    {
        if (!is_array($raw) || $raw === []) {
            return null;
        }
        $picked = [];
        foreach ($raw as $code) {
            $reason = is_string($code) ? self::tryFrom($code) : null;
            if ($reason === null) {
                return null;
            }
            $picked[$reason->value] = true;
        }

        return array_values(array_filter(
            self::cases(),
            static fn (self $r): bool => isset($picked[$r->value]),
        ));
    }

    /**
     * @param list<self> $reasons
     */
    public static function join(array $reasons): string
    {
        return implode(',', array_map(static fn (self $r): string => $r->value, $reasons));
    }

    public function label(): string
    {
        return match ($this) {
            self::TooOld => 'Too old',
            self::TooExpensive => 'Too expensive',
            self::WeakensTeam => 'Weakens my team',
            self::WrongPosition => 'Wrong position',
            self::NotRealistic => 'Not realistic',
            self::Other => 'Other',
        };
    }
}
