-- Admin users
CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lưu lịch sử phân tích
CREATE TABLE IF NOT EXISTS analysis_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip VARCHAR(64),
  input_text LONGTEXT NOT NULL,
  output_json LONGTEXT NOT NULL,
  risk_score INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limit theo IP+phút
CREATE TABLE IF NOT EXISTS rate_limits (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip VARCHAR(64) NOT NULL,
  minute_window VARCHAR(20) NOT NULL,
  count INT DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_ip_minute (ip, minute_window)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache post/comment từ Graph API
CREATE TABLE IF NOT EXISTS fb_cache (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cache_key VARCHAR(200) NOT NULL,
  payload LONGTEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_cache_key (cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auto_actions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  object_id VARCHAR(64) NOT NULL,
  object_type ENUM('comment','post') NOT NULL,
  action VARCHAR(32) NOT NULL,               -- replied | hidden | skipped
  risk INT,
  reason VARCHAR(255),
  response_text TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_obj_action (object_id, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE auto_actions
  ADD INDEX idx_created (created_at),
  ADD INDEX idx_risk (risk);

  -- bảng chuẩn cho MySQL/MariaDB
CREATE TABLE IF NOT EXISTS auto_actions (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  object_id    VARCHAR(64) NOT NULL,
  object_type  ENUM('post','comment') NOT NULL,
  action       VARCHAR(32) NOT NULL,
  risk         INT DEFAULT 0,
  reason       VARCHAR(255),
  response_text TEXT,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_object_action (object_id, action),
  KEY idx_created_at (created_at),
  KEY idx_object_type (object_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kb_sources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  platform VARCHAR(16) NOT NULL,               -- 'facebook' | 'web'
  source_name VARCHAR(128) NOT NULL,           -- 'IUH Official'
  trust_level DECIMAL(3,2) NOT NULL DEFAULT 1.00,
  url VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kb_posts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  source_id INT NOT NULL,
  fb_post_id VARCHAR(64),                      -- nếu lấy từ FB
  title VARCHAR(255),
  message_raw MEDIUMTEXT,
  message_clean MEDIUMTEXT,
  topic VARCHAR(64),
  doc_type VARCHAR(32),                        -- announcement/policy/schedule/faq...
  permalink_url VARCHAR(255),
  created_time DATETIME,
  updated_time DATETIME,
  trust_level DECIMAL(3,2) NOT NULL DEFAULT 1.00,
  md5 CHAR(32) NOT NULL,                       -- chống trùng lặp
  UNIQUE KEY u_fb (fb_post_id),
  KEY idx_time (created_time),
  KEY idx_topic (topic),
  CONSTRAINT fk_kb_posts_source FOREIGN KEY (source_id) REFERENCES kb_sources(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kb_chunks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL,
  chunk_idx INT NOT NULL,
  text MEDIUMTEXT,
  text_clean MEDIUMTEXT,
  tokens INT,
  trust_level DECIMAL(3,2) DEFAULT 1.00,
  INDEX idx_post (post_id, chunk_idx),
  FULLTEXT KEY ft_text (text, text_clean),     -- dùng NL search trước, nâng cấp vector sau
  CONSTRAINT fk_kb_chunks_post FOREIGN KEY (post_id) REFERENCES kb_posts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE FULLTEXT INDEX ft_chunk ON kb_chunks (text, text_clean);

ANALYZE TABLE kb_posts;
ANALYZE TABLE kb_chunks;

ALTER TABLE admin_users ADD COLUMN email VARCHAR(255);  
UPDATE admin_users SET email = 'septcomay@gmail.com' WHERE id = 1;

CREATE TABLE IF NOT EXISTS password_reset_tokens (  
    id INT AUTO_INCREMENT PRIMARY KEY,  
    admin_id INT UNSIGNED NOT NULL,  
    token VARCHAR(64) NOT NULL,  
    expires_at DATETIME NOT NULL,  
    used TINYINT DEFAULT 0,  
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,  
    UNIQUE KEY uniq_token (token),  
    KEY idx_expires (expires_at),  
    KEY idx_admin_id (admin_id)  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE password_reset_tokens   
ADD CONSTRAINT fk_reset_admin   
FOREIGN KEY (admin_id) REFERENCES admin_users(id)   
ON DELETE CASCADE;