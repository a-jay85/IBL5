<?php

/************************************************************************/
/* DEPRECATED - Comment Moderation System Removed for Security          */
/*                                                                      */
/* This file previously contained the PHP-Nuke comment moderation       */
/* which had SQL injection vulnerabilities. The entire comment system   */
/* has been decommissioned.                                             */
/************************************************************************/

if (!defined('ADMIN_FILE')) {
    die("Access Denied");
}

// Comment moderation system has been removed - redirect to admin home
header("Location: admin.php");
exit;
