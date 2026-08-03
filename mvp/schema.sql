-- Marine Video Portal (PHP/MySQL edition) — database schema
-- Import this once via: mysql -u USER -p DBNAME < schema.sql

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  is_approved TINYINT(1) NOT NULL DEFAULT 0,
  last_seen DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS collections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS videos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  filename VARCHAR(255) NULL,
  embed_url VARCHAR(500) NULL,
  bunny_video_id VARCHAR(64) NULL,
  thumbnail VARCHAR(255) NULL,
  collection_id INT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('processing','ready','failed') NOT NULL DEFAULT 'ready',
  views INT NOT NULL DEFAULT 0,
  watch_seconds BIGINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS watch_progress (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  video_id INT NOT NULL,
  position_seconds INT NOT NULL DEFAULT 0,
  duration_seconds INT NOT NULL DEFAULT 0,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_video (user_id, video_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS share_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token VARCHAR(64) NOT NULL UNIQUE,
  video_id INT NOT NULL,
  recipient_email VARCHAR(190) NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked TINYINT(1) NOT NULL DEFAULT 0,
  viewed_at DATETIME NULL,
  emailed_at DATETIME NULL,
  created_by VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_email VARCHAR(190) NOT NULL,
  action VARCHAR(100) NOT NULL,
  details TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limits (
  bucket_key VARCHAR(150) PRIMARY KEY,
  hit_count INT NOT NULL DEFAULT 0,
  window_start DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS play_tokens (
  token VARCHAR(64) PRIMARY KEY,
  video_id INT NOT NULL,
  owner_key VARCHAR(190) NOT NULL,
  expires_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS video_views (
  id INT AUTO_INCREMENT PRIMARY KEY,
  video_id INT NOT NULL,
  viewer_email VARCHAR(190) NULL,
  viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
  INDEX idx_viewed_at (viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration for existing installs upgrading from a pre-bunny.net version:
ALTER TABLE videos ADD COLUMN IF NOT EXISTS bunny_video_id VARCHAR(64) NULL AFTER embed_url;

-- Seed default settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('homepage_count', '60'),
  ('theme_preset', 'ocean'),
  ('theme_custom', ''),
  ('site_name', 'Marine Video Portal');
