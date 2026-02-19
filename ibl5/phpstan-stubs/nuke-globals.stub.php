<?php

// PHP-Nuke language constants (loaded at runtime by get_lang())
// Footer
define('_PAGEGENERATION', '');
define('_SECONDS', '');
// Navbar (YourAccount module)
define('_CHANGEYOURINFO', '');
define('_CHANGEHOME', '');
define('_CONFIGCOMMENTS', '');
define('_SELECTTHETHEME', '');
define('_LOGOUTEXIT', '');
define('_RETURNACCOUNT', '');
// Search
define('_ALL', '');
define('_ALLAUTHORS', '');
define('_ALLTOPICS', '');
define('_ARTICLES', '');
define('_ATTACHART', '');
define('_CONTRIBUTEDBY', '');
define('_DELETE', '');
define('_EDIT', '');
define('_MONTH', '');
define('_MONTHS', '');
define('_NEXTMATCHES', '');
define('_NOCOMMENTS', '');
define('_NOMATCHES', '');
define('_NONAME', '');
define('_ON', '');
define('_POSTEDBY', '');
define('_PREVMATCHES', '');
define('_SCOMMENTS', '');
define('_SEARCHIN', '');
define('_SEARCHON', '');
define('_SEARCHRESULTS', '');
define('_SEARCHUSERS', '');
define('_SREPLIES', '');
define('_SREPLY', '');
define('_SSTORIES', '');
define('_SUSERS', '');
define('_TOPIC', '');
define('_UCOMMENT', '');
define('_UCOMMENTS', '');
define('_WEEK', '');
define('_WEEKS', '');
// SeriesRecords / Waivers
define('_LOGININCOR', '');
define('_USERREGLOGIN', '');
// MySQL.php
define('END_TRANSACTION', 1);

// PHP-Nuke core functions

/** @return void */
function loginbox(): void {}

/** @return string|null */
function buildRedirectUrl(): ?string { return null; }

/** @return bool */
function is_user(mixed $cookie): bool { return false; }

/**
 * @return array<int, string>
 */
function cookiedecode(mixed $cookie): array { return []; }

/** @return string */
function get_theme(): string { return ''; }

/** @return void */
function get_lang(string $module): void {}

/** @return void */
function include_secure(string $file): void {}

/** @return void */
function blocks(string $position): void {}

/** @return void */
function message_box(): void {}

/** @return void */
function online(): void {}

/** @return void */
function themeheader(): void {}

/** @return void */
function themefooter(): void {}

/**
 * @return array<string, mixed>|null
 */
function getusrinfo(mixed $user): ?array { return null; }

/** @return bool */
function is_admin(mixed $admin): bool { return false; }

/** @return string */
function formatTimestamp(int|string $timestamp): string { return ''; }
