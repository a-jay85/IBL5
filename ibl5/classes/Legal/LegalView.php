<?php

declare(strict_types=1);

namespace Legal;

/**
 * Static Privacy Policy and Terms of Service pages.
 *
 * Google's OAuth consent screen links to both, so the URLs
 * (modules.php?name=Privacy, modules.php?name=Terms) must stay stable.
 */
final class LegalView
{
    public const LAST_UPDATED = 'October 28, 2026';

    public function renderPrivacy(): string
    {
        ob_start();
        ?>
<div class="ibl-card max-w-3xl mx-auto">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">Privacy Policy</h2>
    </div>
    <div class="ibl-card__body text-left">
        <p class="mb-4">Last updated: <?= self::LAST_UPDATED ?></p>
        <p class="mb-4">The Internet Basketball League (IBL) is a hobby sim league run by volunteers at iblhoops.net. We don't sell or rent any data, and we don't show ads.</p>

        <h3 class="mb-2 font-semibold">What we store</h3>
        <ul class="mb-6 list-disc pl-5">
            <li class="mb-1">Your account details: your username, the team you run, and any contact details you give the league.</li>
            <li class="mb-1">What you do in the league: trades, depth charts, votes, free agency offers, and similar actions.</li>
            <li class="mb-1">Cookies that keep you signed in and remember your settings.</li>
            <li class="mb-1">Normal server logs, such as IP address and pages visited, used to keep the site running and secure.</li>
        </ul>

        <h3 class="mb-2 font-semibold">Google Sheets Sync</h3>
        <p class="mb-4">If you choose to connect Google Sheets, IBL asks Google for one permission: to see and edit only the files IBL creates in your Google Drive. IBL can't see any of your other files.</p>
        <ul class="mb-4 list-disc pl-5">
            <li class="mb-1">IBL creates one spreadsheet in your Drive and keeps it filled with league player data.</li>
            <li class="mb-1">We store an encrypted Google token so we can update that spreadsheet, plus the spreadsheet's ID and link.</li>
            <li class="mb-1">We don't read, store, or share anything else from your Google account.</li>
            <li class="mb-1">You can disconnect anytime from the API Keys page. That deletes the token and cancels IBL's access. The spreadsheet stays in your Drive and is yours to keep or delete.</li>
        </ul>
        <p class="mb-6">IBL's use of information received from Google APIs will adhere to the <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener noreferrer">Google API Services User Data Policy</a>, including the Limited Use requirements.</p>

        <h3 class="mb-2 font-semibold">Who can see your data</h3>
        <p class="mb-6">League admins can see what's stored to run the league. Your league activity, such as trades and rosters, is public on the site. We don't give your data to anyone else unless the law requires it.</p>

        <h3 class="mb-2 font-semibold">Removing your data</h3>
        <p class="mb-6">To have your account or data removed, ask the commissioner on the league Discord.</p>

        <h3 class="mb-2 font-semibold">Changes</h3>
        <p>If this policy changes, we'll update this page and the date at the top.</p>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    public function renderTerms(): string
    {
        ob_start();
        ?>
<div class="ibl-card max-w-3xl mx-auto">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">Terms of Service</h2>
    </div>
    <div class="ibl-card__body text-left">
        <p class="mb-4">Last updated: <?= self::LAST_UPDATED ?></p>
        <p class="mb-6">By using iblhoops.net you agree to these terms. If you don't agree, please don't use the site.</p>

        <h3 class="mb-2 font-semibold">The league</h3>
        <p class="mb-6">The Internet Basketball League (IBL) is a free hobby sim league run by volunteers. It has no connection to the NBA or any real team or player.</p>

        <h3 class="mb-2 font-semibold">Your account</h3>
        <ul class="mb-6 list-disc pl-5">
            <li class="mb-1">Keep your login and API keys to yourself. You're responsible for what happens under your account.</li>
            <li class="mb-1">Follow the league rules and treat other GMs with respect.</li>
            <li class="mb-1">Don't try to break, overload, or get around the site's security.</li>
            <li class="mb-1">League admins can remove an account or undo actions that break these terms or the league rules.</li>
        </ul>

        <h3 class="mb-2 font-semibold">Google Sheets Sync and the API</h3>
        <p class="mb-6">The player export, the API, and Google Sheets Sync are for your own league use. Don't use them to overload the site. We may limit or turn off any of them at any time. Our <a href="modules.php?name=Privacy">Privacy Policy</a> explains what we store when you connect Google Sheets.</p>

        <h3 class="mb-2 font-semibold">No warranty</h3>
        <p class="mb-6">The site is provided as is. Stats, ratings, and exports may have mistakes or go down. To the extent the law allows, IBL and its volunteers aren't liable for any loss from using the site.</p>

        <h3 class="mb-2 font-semibold">Changes</h3>
        <p>We may update these terms. When we do, we'll change this page and the date at the top. Using the site after a change means you accept the new terms.</p>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
