<?php

/************************************************************************/
/* DEPRECATED - Comment System Removed for Security                     */
/*                                                                      */
/* This file previously contained the PHP-Nuke comment system which     */
/* had 39+ SQL injection vulnerabilities. The entire comment system     */
/* has been decommissioned.                                             */
/*                                                                      */
/* If comments functionality is needed in the future, it should be      */
/* re-implemented with:                                                 */
/* - Prepared statements for all database queries                       */
/* - CSRF protection on all forms                                       */
/* - Proper input validation and output escaping                        */
/************************************************************************/

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

// Comment system has been removed - this is now a no-op
// The file remains to prevent include errors in legacy code
