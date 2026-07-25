<?php

declare(strict_types=1);

namespace EventLog;

/**
 * Normalises a sanitised route-name token to snake_case.
 *
 * Pure: no I/O, no globals. Input must already have been character-class
 * filtered ([A-Za-z0-9_-]) by the caller — this class only folds the result.
 *
 * Normalisation steps (in this exact order):
 *   1. Replace hyphens with underscores.
 *   2. Acronym split: GLM → G_LM then GM_ContactList → GM_Contact_List (first).
 *   3. Camel boundary: lowercase→Uppercase → insert underscore.
 *   4. Lower-case the whole string.
 *   5. Collapse consecutive underscores; trim leading/trailing underscores.
 *   6. Return null if the result is empty, else the string.
 *
 * Truncation is NOT applied here — the caller truncates after normalising
 * (normalisation can lengthen a value, so truncating first would clip tokens).
 */
final class RouteNameNormalizer
{
    public static function normalize(string $raw): ?string
    {
        // Step 1: hyphens → underscores.
        $s = str_replace('-', '_', $raw);

        // Step 2: acronym rule — must run first.
        // GLMContactList → GLM_ContactList; GMContactList → GM_ContactList.
        $s = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $s);

        // Step 3: camel boundary — lowercase/digit followed by uppercase.
        $s = (string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $s);

        // Step 4: lower-case.
        $s = strtolower($s);

        // Step 5: collapse and trim.
        $s = (string) preg_replace('/_+/', '_', $s);
        $s = trim($s, '_');

        // Step 6: null for empty.
        return $s === '' ? null : $s;
    }
}
