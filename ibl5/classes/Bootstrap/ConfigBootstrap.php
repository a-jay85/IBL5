<?php

declare(strict_types=1);

namespace Bootstrap;

use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

/**
 * Configuration bootstrap: protected globals denylist, $_REQUEST extraction,
 * config.php loading, database init, nuke_config query, error reporting.
 *
 * Extracted from mainfile.php lines 166-293.
 */
class ConfigBootstrap implements BootstrapStepInterface
{
    private string $basePath;

    /** @var list<string> Critical globals that must never be overwritten via $_REQUEST */
    private const PROTECTED_GLOBALS = [
        // Database credentials (from config.php)
        'dbhost', 'dbuname', 'dbpass', 'dbname', 'prefix', 'user_prefix',
        // Database connection objects
        'db', 'mysqli_db',
        // Authentication state
        'user', 'cookie', 'userinfo',
        // PHP-Nuke core configuration
        'nukeurl', 'sitename', 'adminmail',
        // Session/superglobals
        '_SESSION', '_COOKIE', '_SERVER', '_ENV', '_FILES', '_GET', '_POST', '_REQUEST',
        // Internal PHP
        'GLOBALS', 'this',
        // League context
        'leagueContext',
        // Authentication service
        'authService',
    ];

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        $this->extractRequestToGlobals();
        $this->loadConfig();
        $this->loadDatabase();
        $this->loadNukeConfig($container);
        $this->configureErrorReporting();
    }

    private function extractRequestToGlobals(): void
    {
        /** @var array<string, string> */
        $sanitizeRules = ['newlang' => '/[a-z][a-z]/i', 'redirect' => '/[a-z0-9]*/i'];

        foreach ($_REQUEST as $key => $value) {
            if (in_array($key, self::PROTECTED_GLOBALS, true)) {
                continue;
            }
            if (!is_string($key)) {
                continue;
            }
            if (!is_string($value)) {
                $GLOBALS[$key] = $value;
                continue;
            }
            if (!isset($sanitizeRules[$key]) || preg_match($sanitizeRules[$key], $value) === 1) {
                $GLOBALS[$key] = $value;
            }
        }
    }

    private function loadConfig(): void
    {
        require_once $this->basePath . '/config.php';

        if (!isset($GLOBALS['dbname']) || $GLOBALS['dbname'] === '' || $GLOBALS['dbname'] === false) {
            echo "<br><br><center><img src=images/logo.gif><br><br><b>There seems that PHP-Nuke isn't installed yet.<br>(The values in config.php file are the default ones)<br><br>You can proceed with the <a href='./install/index.php'>web installation</a> now.</center></b>";
            exit();
        }
    }

    private function loadDatabase(): void
    {
        require_once $this->basePath . '/db/db.php';
    }

    private function loadNukeConfig(ContainerInterface $container): void
    {
        /** @var \MySQL $db */ // @phpstan-ignore varTag.deprecatedClass
        $db = $GLOBALS['db'];
        $rawPrefix = $GLOBALS['prefix'] ?? 'nuke';
        $prefix = is_string($rawPrefix) ? $rawPrefix : 'nuke';

        if (!defined('NUKE_FILE')) {
            define('NUKE_FILE', true);
        }
        $result = $db->sql_query("SELECT * FROM " . $prefix . "_config"); // @phpstan-ignore method.deprecatedClass
        $fetchedRow = $db->sql_fetchrow($result); // @phpstan-ignore method.deprecatedClass

        if (!is_array($fetchedRow)) {
            return;
        }

        /** @var array<string, string|int|float|null> $row */
        $row = $fetchedRow;

        // Extract all nuke_config values to globals (same as current mainfile.php)
        $GLOBALS['sitename'] = filter($row['sitename'], "nohtml");
        $GLOBALS['nukeurl'] = filter($row['nukeurl'], "nohtml");
        $GLOBALS['site_logo'] = filter($row['site_logo'], "nohtml");
        $GLOBALS['slogan'] = filter($row['slogan'], "nohtml");
        $GLOBALS['startdate'] = filter($row['startdate'], "nohtml");
        $GLOBALS['adminmail'] = filter($row['adminmail'], "nohtml");
        $GLOBALS['anonpost'] = intval($row['anonpost']);
        $GLOBALS['Default_Theme'] = filter($row['Default_Theme'], "nohtml");
        $GLOBALS['foot1'] = filter($row['foot1']);
        $GLOBALS['foot2'] = filter($row['foot2']);
        $GLOBALS['foot3'] = filter($row['foot3']);
        $GLOBALS['commentlimit'] = intval($row['commentlimit']);
        $GLOBALS['anonymous'] = filter($row['anonymous'], "nohtml");
        $GLOBALS['minpass'] = intval($row['minpass']);
        $GLOBALS['pollcomm'] = intval($row['pollcomm']);
        $GLOBALS['articlecomm'] = intval($row['articlecomm']);
        $GLOBALS['broadcast_msg'] = intval($row['broadcast_msg']);
        $GLOBALS['my_headlines'] = intval($row['my_headlines']);
        $GLOBALS['top'] = intval($row['top']);
        $GLOBALS['storyhome'] = intval($row['storyhome']);
        $GLOBALS['user_news'] = intval($row['user_news']);
        $GLOBALS['oldnum'] = intval($row['oldnum']);
        $GLOBALS['ultramode'] = intval($row['ultramode']);
        $GLOBALS['banners'] = intval($row['banners']);
        $GLOBALS['backend_title'] = filter($row['backend_title'], "nohtml");
        $GLOBALS['backend_language'] = filter($row['backend_language'], "nohtml");
        $GLOBALS['language'] = filter($row['language'], "nohtml");
        $GLOBALS['locale'] = filter($row['locale'], "nohtml");
        $GLOBALS['multilingual'] = intval($row['multilingual']);
        $GLOBALS['useflags'] = intval($row['useflags']);
        $GLOBALS['notify'] = intval($row['notify']);
        $GLOBALS['notify_email'] = filter($row['notify_email'], "nohtml");
        $GLOBALS['notify_subject'] = filter($row['notify_subject'], "nohtml");
        $GLOBALS['notify_message'] = filter($row['notify_message'], "nohtml");
        $GLOBALS['notify_from'] = filter($row['notify_from'], "nohtml");
        $GLOBALS['moderate'] = intval($row['moderate']);
        $GLOBALS['admingraphic'] = intval($row['admingraphic']);
        $GLOBALS['CensorMode'] = intval($row['CensorMode']);
        $GLOBALS['CensorReplace'] = filter($row['CensorReplace'], "nohtml");
        $GLOBALS['copyright'] = filter($row['copyright']);
        $GLOBALS['Version_Num'] = floatval($row['Version_Num']);
        $nukeurl = $GLOBALS['nukeurl'];
        $GLOBALS['domain'] = is_string($nukeurl) ? str_replace("http://", "", $nukeurl) : '';
        $GLOBALS['display_errors'] = filter($row['display_errors']);
        $GLOBALS['nuke_editor'] = intval($row['nuke_editor']);
        $GLOBALS['mtime'] = microtime(true);
        $GLOBALS['start_time'] = $GLOBALS['mtime'];
        $GLOBALS['pagetitle'] = "";

        $container->set('nukeConfig', $row);
    }

    private function configureErrorReporting(): void
    {
        error_reporting(E_ERROR);
        $displayErrors = $GLOBALS['display_errors'] ?? '';
        if ($displayErrors === 1 || $displayErrors === '1') {
            @ini_set('display_errors', '1');
        } else {
            @ini_set('display_errors', '0');
        }
    }
}
