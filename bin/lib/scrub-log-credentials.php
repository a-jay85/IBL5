<?php

declare(strict_types=1);

/**
 * scrub-log-credentials.php — the NDJSON-rewriting core of bin/scrub-log-credentials.
 *
 * Ported verbatim from the jq filter that used to live inline in that script.
 * The prod host is a shared cPanel box with no jq and no way to install one that
 * survives a host reset; it always has PHP, because PHP is the application's own
 * runtime. Both local and --prod modes now drive this one file, so a local
 * dry-run actually exercises the prod code path.
 *
 * Deliberately standalone: no autoload, no config, no Composer, no I/O beyond the
 * two files named on the command line. bin/scrub-log-credentials base64-ships this
 * single file over SSH, so it must run on its own.
 *
 * Usage:  php scrub-log-credentials.php <input-file> <output-file>
 * Stdout: one line, "HITS:" followed by comma-separated 1-based line numbers of
 *         the records that changed (just "HITS:" when nothing changed).
 * Exit:   0 on success, 1 on a usage or I/O error.
 */

const REDACTED = '[REDACTED: frame arguments removed by bin/scrub-log-credentials]';

/**
 * Rewrite each stack frame to drop its call arguments, keeping frame identity.
 *
 * Mirrors the jq `strip_args` def: two anchored substitutions per line, first
 * match only, applied in order. Anchoring means at most one match per line, so
 * the limit of 1 is belt-and-braces rather than load-bearing.
 *
 * On a PCRE failure (a pathological line hitting the backtrack limit) the line is
 * left as-is, which fails is_clean() and sends the whole trace to REDACTED. That
 * fails safe: it can over-redact, never under-redact.
 */
function strip_args(string $trace): string
{
    $lines = explode("\n", $trace);

    foreach ($lines as $i => $line) {
        // "#12 /app/Foo.php(78): Foo->bar('secret')" -> "#12 /app/Foo.php(78): Foo->bar()"
        $stripped = preg_replace('/^(?<h>#[0-9]+ .*?\([0-9]+\): [^(]*)\(.*$/', '${1}()', $line, 1);
        if ($stripped === null) {
            $stripped = $line;
        }

        // "#13 [internal function]: Foo->bar('secret')" -> "#13 [internal function]: Foo->bar()"
        $internal = preg_replace('/^(?<h>#[0-9]+ \[internal function\]: [^(]*)\(.*$/', '${1}()', $stripped, 1);
        if ($internal === null) {
            $internal = $stripped;
        }

        $lines[$i] = $internal;
    }

    return implode("\n", $lines);
}

/**
 * True when every frame is argument-free — either "#N {main}", a frame ending in
 * "()", or the "#N Foo->bar() at /app/Foo.php:78" shape that
 * Bootstrap\ErrorHandlerRegistrar::buildArgFreeTrace() emits for new records.
 *
 * Mirrors the jq `is_clean` def. Anything that does not match is assumed to still
 * carry arguments, so the caller redacts the trace wholesale.
 */
function is_clean(string $trace): bool
{
    // jq's split("") yields [], and all/1 over [] is true, so the jq filter
    // treated an empty trace as clean. explode() yields [""] instead, which
    // matches no frame pattern and would redact a string holding no arguments.
    // Keep jq's answer — it is also the right one.
    if ($trace === '') {
        return true;
    }

    foreach (explode("\n", $trace) as $line) {
        if (preg_match('/^#[0-9]+ \{main\}$/', $line) === 1) {
            continue;
        }
        if (preg_match('/^#[0-9]+ .*\(\)$/', $line) === 1) {
            continue;
        }
        if (preg_match('/^#[0-9]+ .*\(\) at .+:[0-9]+$/', $line) === 1) {
            continue;
        }

        return false;
    }

    return true;
}

/**
 * Mirrors the jq `process_trace` def.
 *
 * @return array{0: string, 1: bool} [new trace, changed]
 */
function process_trace(string $old): array
{
    if ($old === REDACTED) {
        return [$old, false];
    }

    $new = strip_args($old);
    if (is_clean($new)) {
        return [$new, $new !== $old];
    }

    return [REDACTED, $old !== REDACTED];
}

/**
 * Scrub one raw NDJSON line (no trailing newline).
 *
 * Unchanged records are returned as the original bytes rather than re-encoded.
 * The jq filter re-encoded every line through `tojson`; PHP's json_encode makes
 * different-but-equivalent escaping choices, so a re-encode of an untouched
 * record would rewrite the whole file for no reason and bury the real edits.
 * Raw passthrough keeps the scrubbed diff auditable, and makes a re-run a
 * genuine no-op.
 *
 * @return array{0: string, 1: bool} [output line, hit]
 */
function process_line(string $raw): array
{
    // Objects, not associative arrays: assoc mode turns `{}` into `[]` on
    // re-encode, silently corrupting empty objects elsewhere in a hit record.
    $parsed = json_decode($raw);
    if ($parsed === null || json_last_error() !== JSON_ERROR_NONE) {
        return [$raw, false];
    }

    if (isset($parsed->context->trace) && is_string($parsed->context->trace)) {
        $old = $parsed->context->trace;
        [$new, $changed] = process_trace($old);
        if (!$changed) {
            return [$raw, false];
        }
        $parsed->context->trace = $new;
    } elseif (isset($parsed->context->exception->trace) && is_string($parsed->context->exception->trace)) {
        $old = $parsed->context->exception->trace;
        [$new, $changed] = process_trace($old);
        if (!$changed) {
            return [$raw, false];
        }
        $parsed->context->exception->trace = $new;
    } else {
        return [$raw, false];
    }

    // Match jq's output conventions: bare slashes, literal UTF-8, `1.0` stays `1.0`.
    $encoded = json_encode(
        $parsed,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
    );
    if ($encoded === false) {
        // Never emit a half-written record — keep the original bytes and no hit.
        return [$raw, false];
    }

    return [$encoded, true];
}

// ---------------------------------------------------------------------------
// Entry point
// ---------------------------------------------------------------------------

$inPath  = $argv[1] ?? null;
$outPath = $argv[2] ?? null;

if ($inPath === null || $outPath === null) {
    fwrite(STDERR, "usage: php scrub-log-credentials.php <input-file> <output-file>\n");
    exit(1);
}

$in = @fopen($inPath, 'rb');
if ($in === false) {
    fwrite(STDERR, "scrub-log-credentials: cannot read {$inPath}\n");
    exit(1);
}

$out = @fopen($outPath, 'wb');
if ($out === false) {
    fclose($in);
    fwrite(STDERR, "scrub-log-credentials: cannot write {$outPath}\n");
    exit(1);
}

$lineNo = 0;
$hits   = [];

// Streamed a line at a time — prod logs are large and this runs on a shared host.
while (($line = fgets($in)) !== false) {
    $lineNo++;

    // Preserve the record's own terminator. A final line with no trailing newline
    // stays that way instead of being dropped or grown one.
    $eol = '';
    if (substr($line, -1) === "\n") {
        $eol  = "\n";
        $line = substr($line, 0, -1);
    }

    [$scrubbed, $hit] = process_line($line);
    if ($hit) {
        $hits[] = $lineNo;
    }

    if (fwrite($out, $scrubbed . $eol) === false) {
        fclose($in);
        fclose($out);
        fwrite(STDERR, "scrub-log-credentials: write failed for {$outPath}\n");
        exit(1);
    }
}

fclose($in);
if (!fclose($out)) {
    fwrite(STDERR, "scrub-log-credentials: could not flush {$outPath}\n");
    exit(1);
}

echo 'HITS:' . implode(',', $hits) . "\n";
exit(0);
