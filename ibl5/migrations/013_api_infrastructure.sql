-- Migration 013: API key authentication and rate limiting infrastructure
--
-- Creates tables for API key management and per-key rate limiting.
-- API keys are stored as SHA-256 hashes (never plaintext).

-- ---------------------------------------------------------------------------
-- Table 1: API Keys
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ibl_api_keys (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_hash CHAR(64) NOT NULL UNIQUE COMMENT 'SHA-256 hash of the API key',
  key_prefix CHAR(8) NOT NULL COMMENT 'First 8 chars of key for log identification',
  owner_name VARCHAR(64) NOT NULL COMMENT 'Human-readable owner (e.g. Discord Bot - MJ)',
  permission_level ENUM('public', 'team_owner', 'commissioner') NOT NULL DEFAULT 'public',
  rate_limit_tier ENUM('standard', 'elevated', 'unlimited') NOT NULL DEFAULT 'standard',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_used_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_key_hash (key_hash),
  INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Table 2: Rate Limiting (sliding window per minute)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ibl_api_rate_limits (
  api_key_hash CHAR(64) NOT NULL,
  window_start TIMESTAMP NOT NULL COMMENT 'Start of the 1-minute window',
  request_count INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (api_key_hash, window_start),
  INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
