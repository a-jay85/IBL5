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
 *
 * Pass $includeNukeConfig = false for API bootstrap: nuke_config queries call
 * filter() which is only defined in the web (mainfile.php) context.
 */
class ConfigBootstrap implements BootstrapStepInterface
{
    private string $basePath;
    private bool $includeNukeConfig;

    /** @var array<string, string> Allowlist of $_REQUEST keys that may flow into $GLOBALS, with their sanitize regex. */
    private const ALLOWED_REQUEST_TO_GLOBALS = [
        'newlang'  => '/^[a-z]{2}$/i',
        'redirect' => '/^[a-z0-9]*$/i',
    ];

    public function __construct(string $basePath, bool $includeNukeConfig = true)
    {
        $this->basePath = $basePath;
        $this->includeNukeConfig = $includeNukeConfig;
    }

    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        $this->extractRequestToGlobals();
        $this->loadConfig();
        $this->loadDatabase();
        if ($this->includeNukeConfig) {
            $this->loadNukeConfig($container);
        }
        $this->configureErrorReporting();
        $this->registerSharedServices($container);
    }

    private function extractRequestToGlobals(): void
    {
        foreach (self::ALLOWED_REQUEST_TO_GLOBALS as $key => $regex) {
            if (!isset($_REQUEST[$key]) || !is_string($_REQUEST[$key])) {
                continue;
            }
            if (preg_match($regex, $_REQUEST[$key]) === 1) {
                $GLOBALS[$key] = $_REQUEST[$key];
            }
        }
    }

    private function loadConfig(): void
    {
        // config.php assigns variables at file scope. When required from within a
        // class method, those assignments are method-local unless declared global here.
        global $dbhost, $dbuname, $dbpass, $dbname, $prefix, $user_prefix, $dbtype;
        global $sitekey, $subscription_url, $admin_file, $display_errors;
        global $reasons, $badreasons, $AllowableHTML, $CensorList, $tipath, $commercial_license;

        /** @phpstan-ignore ibl.requireOnce (defines global database/config state; not a class) */
        require_once $this->basePath . '/config.php';

        if (!isset($GLOBALS['dbname']) || $GLOBALS['dbname'] === '' || $GLOBALS['dbname'] === false) {
            echo "<br><br><center><img src=images/logo.gif alt=\"\"><br><br><b>There seems that PHP-Nuke isn't installed yet.<br>(The values in config.php file are the default ones)<br><br>You can proceed with the <a href='./install/index.php'>web installation</a> now.</center></b>";
            exit();
        }
    }

    private function loadDatabase(): void
    {
        // db.php reads config variables and writes $db / $mysqli_db — declare all global
        // so values flow in from loadConfig() and out to the rest of the application.
        global $dbhost, $dbuname, $dbpass, $dbname, $dbtype, $db, $mysqli_db;

        /** @phpstan-ignore ibl.requireOnce (initializes $db and $mysqli_db globals; not a class) */
        require_once $this->basePath . '/db/db.php';

        \Logging\LoggerFactory::fromConfig();
    }

    private function loadNukeConfig(ContainerInterface $container): void
    {
        /** @var \Database\MySQL $db */
        $db = $GLOBALS['db'];
        $rawPrefix = $GLOBALS['prefix'] ?? 'nuke';
        $prefix = is_string($rawPrefix) ? $rawPrefix : 'nuke';

        if (!defined('NUKE_FILE')) {
            define('NUKE_FILE', true);
        }
        $result = $db->sql_query("SELECT * FROM " . $prefix . "_config");
        $fetchedRow = $db->sql_fetchrow($result);

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
        $GLOBALS['Default_Theme'] = filter($row['default_theme'], "nohtml");
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
        $GLOBALS['CensorMode'] = intval($row['censor_mode']);
        $GLOBALS['CensorReplace'] = filter($row['censor_replace'], "nohtml");
        $GLOBALS['copyright'] = filter($row['copyright']);
        $GLOBALS['Version_Num'] = floatval($row['version_num']);
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

    private function registerSharedServices(ContainerInterface $container): void
    {
        $container->set('season', static function (): \Season\Season {
            /** @var \mysqli $db */
            $db = $GLOBALS['mysqli_db'];
            return new \Season\Season($db);
        });

        $container->set('mysqli_db', static function (): \mysqli {
            /** @var \mysqli $db */
            $db = $GLOBALS['mysqli_db'];
            return $db;
        });

        $channels = ['app', 'audit', 'db', 'discord', 'draft', 'admin', 'perf'];
        foreach ($channels as $channel) {
            $container->set(
                "logger.{$channel}",
                static fn (): \Psr\Log\LoggerInterface => \Logging\LoggerFactory::getChannel($channel),
            );
        }
    }
}
