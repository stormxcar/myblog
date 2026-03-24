-- Email verification columns for production flow
-- Safe to run multiple times on MySQL 8+ where IF NOT EXISTS is supported.

ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token_hash VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token_expires_at DATETIME NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_sent_at DATETIME NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verified_at DATETIME NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verified_by_admin_id BIGINT UNSIGNED NULL;

CREATE INDEX IF NOT EXISTS idx_users_verification_token_hash ON users(verification_token_hash);
CREATE INDEX IF NOT EXISTS idx_users_is_verified ON users(is_verified);
