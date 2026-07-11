-- Migration 154: Product-analytics request event log
--
-- Records one row per instrumented web request (route/module, authenticated
-- user, resolved team, method, referer, user-agent) so most/least-used paths
-- and per-GM journeys can be reconstructed. Written by RequestEventLoggingBootstrap
-- (runs before the PageCache short-circuit, so cached pageviews are still counted).
-- Nullable identity columns = anonymous (logged-out) requests.

-- ---------------------------------------------------------------------------
-- Table: Request Events
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ibl_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_uri VARCHAR(512) NOT NULL COMMENT 'Raw REQUEST_URI, truncated to 512 chars',
  route_name VARCHAR(64) NULL DEFAULT NULL COMMENT 'Module route from ?name= (raw, sanitized+truncated in PHP); NULL for non-module pages',
  http_method VARCHAR(10) NOT NULL DEFAULT '' COMMENT 'REQUEST_METHOD (GET/POST/...)',
  username VARCHAR(60) NULL DEFAULT NULL COMMENT 'Authenticated username; NULL when anonymous',
  team_id INT NULL DEFAULT NULL COMMENT 'Resolved current team id; NULL when anonymous or unresolved',
  referer VARCHAR(512) NULL DEFAULT NULL COMMENT 'HTTP_REFERER, truncated to 512 chars; NULL if absent',
  user_agent VARCHAR(512) NULL DEFAULT NULL COMMENT 'HTTP_USER_AGENT, truncated to 512 chars; NULL if absent',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Request timestamp',
  INDEX idx_created_at (created_at),
  INDEX idx_team_id (team_id),
  INDEX idx_route_name (route_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
