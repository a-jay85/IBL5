-- Drop the legacy PHP-Nuke article comment table.
-- Comment system was already removed (News/article.php:80).
-- searchComments() now returns empty results; comment count hardcoded to 0.
DROP TABLE IF EXISTS nuke_comments;
