-- MADAPT ULDs Professional v2.0
-- Fresh installation for: u619448402_uldspro
-- Import this file into the target database from phpMyAdmin.
-- Change the initial admin password immediately after first login.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NULL,
  profile_photo VARCHAR(255) NULL,
  role ENUM('ADMIN','SUPERVISOR','OPERATOR') NOT NULL DEFAULT 'OPERATOR',
  active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  KEY idx_users_role_active (role,active),
  KEY idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uld_stock (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  uld_type VARCHAR(10) NOT NULL,
  opening_stock INT NOT NULL DEFAULT 0,
  current_stock INT NOT NULL DEFAULT 0,
  minimum_level INT NOT NULL DEFAULT 10,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uld_stock_type (uld_type),
  KEY idx_uld_stock_current_min (current_stock,minimum_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uld_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  movement_type ENUM('IN','OUT') NOT NULL,
  uld_type VARCHAR(10) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  reference VARCHAR(255) NULL,
  remarks TEXT NULL,
  flight_number VARCHAR(40) NULL,
  user_name VARCHAR(150) NULL,
  user_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_movements_uld_date (uld_type,created_at),
  KEY idx_movements_type_date (movement_type,created_at),
  KEY idx_movements_flight (flight_number),
  KEY idx_movements_user (user_id),
  CONSTRAINT fk_movements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS madapt_settings (
  setting_key VARCHAR(80) NOT NULL,
  setting_value TEXT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS madapt_flights (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  flight_number VARCHAR(40) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_flight_number (flight_number),
  KEY idx_flight_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS madapt_alert_emails (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_alert_email (email),
  KEY idx_alert_email_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS madapt_portals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  url VARCHAR(500) NOT NULL,
  description VARCHAR(500) NULL,
  logo_path VARCHAR(500) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_portals_active_sort (active,sort_order),
  KEY idx_portals_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id BIGINT UNSIGNED NULL,
  details JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user_date (user_id,created_at),
  KEY idx_audit_entity (entity_type,entity_id),
  KEY idx_audit_action_date (action,created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO uld_stock (uld_type,opening_stock,current_stock,minimum_level)
VALUES ('AKE',10,10,10),('PAJ',10,10,10),('PMC',10,10,5),('PAG',10,10,5)
ON DUPLICATE KEY UPDATE uld_type=VALUES(uld_type);

INSERT INTO madapt_settings (setting_key,setting_value) VALUES
('company_name','MADAPT ULDs'),
('support_email',''),
('support_phone',''),
('website',''),
('airport_location','MADAPT'),
('low_stock_alerts','1'),
('new_user_alerts','0'),
('password_reset_alerts','0'),
('low_stock_email_subject','MADAPT ULDs - Low Stock Alert'),
('low_stock_email_message','Dear Team,\n\nThis is an automatic notification from MADAPT ULDs Inventory Management.\n\nULD {ULD_TYPE} has reached or fallen below its minimum stock level.\n\nCurrent stock: {CURRENT_STOCK}\nMinimum level: {MINIMUM_LEVEL}\n\nPlease review the ULD inventory and take the necessary action.\n\nDate: {DATE}\nTime: {TIME}\n\nThis is an automated message. Please do not reply directly to this email.\n\nKind regards,\nMADAPT ULDs Inventory Management'),
('primary','#087a46'),
('accent','#1b8b5b'),
('sidebar','#006b3c'),
('logo_path',''),
('aircraft_path','')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

INSERT INTO madapt_flights (flight_number,active) VALUES ('ET740',1),('ET741',1)
ON DUPLICATE KEY UPDATE flight_number=VALUES(flight_number);

INSERT INTO madapt_portals (name,url,description,logo_path,active,sort_order)
SELECT 'MADAPT','https://madapt.es','MADAPT main portal','',1,1
WHERE NOT EXISTS (SELECT 1 FROM madapt_portals WHERE name='MADAPT');

INSERT INTO users (username,password_hash,full_name,email,role,active)
SELECT 'admin','$2y$12$Fb4j2Yl63dbiPnQcpbDlmODD63nw5MeUSaGfhTkWcBCvnZZD9/qda','System Administrator',NULL,'ADMIN',1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='admin');

SET FOREIGN_KEY_CHECKS=1;

-- Initial administrator:
-- Username: admin
-- Temporary password: ChangeMe123!
-- CHANGE IT immediately after first login.
