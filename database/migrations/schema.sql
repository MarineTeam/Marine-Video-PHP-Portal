
CREATE TABLE IF NOT EXISTS viewers (
  id VARCHAR(36) PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  name VARCHAR(255) NULL,
  is_approved TINYINT(1) DEFAULT 0,
  tags JSON NULL,
  last_seen_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS collections (
  id VARCHAR(36) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS videos_meta (
  guid VARCHAR(100) PRIMARY KEY,
  collection_id VARCHAR(36) NULL,
  title VARCHAR(500) NULL,
  watermark_mode VARCHAR(20) DEFAULT 'default',
  custom_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS bundles (
  id VARCHAR(36) PRIMARY KEY,
  recipient_email VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_recipient (recipient_email)
);
CREATE TABLE IF NOT EXISTS shares (
  id VARCHAR(36) PRIMARY KEY,
  token VARCHAR(64) NOT NULL UNIQUE,
  video_guid VARCHAR(100) NOT NULL,
  recipient_email VARCHAR(255) NOT NULL,
  bundle_id VARCHAR(36) NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked TINYINT(1) DEFAULT 0,
  watermark_override VARCHAR(20) DEFAULT 'default',
  view_count INT DEFAULT 0,
  last_viewed_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_recipient (recipient_email),
  KEY idx_video (video_guid),
  KEY idx_bundle (bundle_id)
);
CREATE TABLE IF NOT EXISTS watch_progress (
  id INT AUTO_INCREMENT PRIMARY KEY,
  viewer_email VARCHAR(255) NOT NULL,
  video_guid VARCHAR(100) NOT NULL,
  progress_seconds INT DEFAULT 0,
  progress_percent FLOAT DEFAULT 0,
  completed TINYINT(1) DEFAULT 0,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_viewer_video (viewer_email, video_guid)
);
CREATE TABLE IF NOT EXISTS viewer_groups (
  id VARCHAR(36) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  emails JSON NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS private_lists (
  video_guid VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL,
  share_id VARCHAR(36) NOT NULL,
  PRIMARY KEY (video_guid,email)
);
CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(100) PRIMARY KEY,
  v JSON NOT NULL
);
CREATE TABLE IF NOT EXISTS audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_email VARCHAR(255) NULL,
  action VARCHAR(100) NULL,
  payload JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
