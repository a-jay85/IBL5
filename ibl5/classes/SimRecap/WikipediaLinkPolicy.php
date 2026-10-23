<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * Decides whether generator-written recap prose may carry a URL.
 *
 * The only URL shape allowed is a masked English Wikipedia article link,
 * `[text](<https://en.wikipedia.org/wiki/PATH>)`. The policy is fail-closed:
 * it rejects prose that carries any other link shape, and it never strips one.
 *
 * It sees only the prose string. It holds no DB handle, session, actor, or
 * config, so the same input always gets the same verdict.
 *
 * The team and box-score links in each game's footer are built by
 * RecapDocument at read time from typed row fields. They never pass through here.
 */
final class WikipediaLinkPolicy
{
    /** `[text](<https://en.wikipedia.org/wiki/PATH>)`, the only URL shape prose may carry. */
    private const ALLOWED_LINK = '~\[[^\[\]\n]+\]\(<https://en\.wikipedia\.org/wiki/[\p{L}\p{N}_\-.,()\'!\~:%#]+>\)~u';

    /** Anything URL-shaped left after the allowed links are removed. */
    private const LEFTOVER = '~\]\(|[a-z][a-z0-9+.\-]*://|<\s*[a-z][a-z0-9+.\-]*:|\(\s*<?//~iu';

    /**
     * Null when $prose carries no URL except allowed Wikipedia links, else a reason string.
     */
    public static function violation(string $prose): ?string
    {
        $remainder = preg_replace(self::ALLOWED_LINK, '', $prose);
        if ($remainder === null) {
            return 'prose is not valid UTF-8';
        }

        if (preg_match(self::LEFTOVER, $remainder, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $byteOffset = $match[0][1];
        $charOffset = mb_strlen(substr($remainder, 0, $byteOffset));

        return 'link not allowed near: ' . mb_substr($remainder, max(0, $charOffset - 20), 80);
    }
}
