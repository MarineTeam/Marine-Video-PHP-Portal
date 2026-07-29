
CREATE TABLE IF NOT EXISTS approved_viewers (
 id INT AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(255) UNIQUE NOT NULL,
 display_name VARCHAR(255),
 tags JSON,
 last_seen_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS collections (
 id VARCHAR(64) PRIMARY KEY,
 name VARCHAR(255) NOT NULL,
 video_count INT DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS shares (
 id VARCHAR(64) PRIMARY KEY,
 video_id VARCHAR(128) NOT NULL,
 video_title VARCHAR(512),
 recipient_email VARCHAR(255) NOT NULL,
 token VARCHAR(128) UNIQUE NOT NULL,
 expires_at DATETIME NOT NULL,
 revoked TINYINT(1) DEFAULT 0,
 view_count INT DEFAULT 0,
 last_viewed_at DATETIME NULL,
 playback_progress INT DEFAULT 0,
 playback_completed TINYINT(1) DEFAULT 0,
 watermark_mode VARCHAR(16) DEFAULT 'default',
 bundle_id VARCHAR(64),
 created_by VARCHAR(255),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_recipient (recipient_email),
 INDEX idx_video (video_id)
);
CREATE TABLE IF NOT EXISTS bundles (
 id VARCHAR(64) PRIMARY KEY,
 recipient_email VARCHAR(255) NOT NULL,
 share_ids JSON,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_recipient (recipient_email)
);
CREATE TABLE IF NOT EXISTS watch_progress (
 id INT AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(255) NOT NULL,
 video_id VARCHAR(128) NOT NULL,
 progress_seconds INT DEFAULT 0,
 percent INT DEFAULT 0,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_email_video (email, video_id)
);
CREATE TABLE IF NOT EXISTS audit_log (
 id INT AUTO_INCREMENT PRIMARY KEY,
 actor_email VARCHAR(255),
 action VARCHAR(64),
 target_type VARCHAR(64),
 target_id VARCHAR(128),
 details JSON,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS settings (
 k VARCHAR(128) PRIMARY KEY,
 v JSON,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS viewer_tags (
 id INT AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(255) NOT NULL,
 tag VARCHAR(64) NOT NULL,
 UNIQUE KEY uniq_email_tag (email, tag)
);
CREATE TABLE IF NOT EXISTS push_subscriptions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(255) NOT NULL,
 endpoint TEXT NOT NULL,
 p256dh TEXT,
 auth TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- SQLite compat view will be created via PHP if needed
