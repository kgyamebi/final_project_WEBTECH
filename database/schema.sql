-- InsideAccra schema. The migrate script selects the database.
-- Timestamps are stored in UTC.

CREATE TABLE IF NOT EXISTS roles (
  id SMALLINT UNSIGNED PRIMARY KEY,
  name VARCHAR(32) NOT NULL UNIQUE,
  description VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role_id SMALLINT UNSIGNED NOT NULL,
  account_status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  notify_disruptions TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_users_email (email),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  skey VARCHAR(64) PRIMARY KEY,
  svalue VARCHAR(255) NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS routes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(160) NOT NULL,
  origin_label VARCHAR(120) NOT NULL,
  destination_label VARCHAR(120) NOT NULL,
  direction VARCHAR(80) NOT NULL,
  description TEXT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  source_name VARCHAR(160) NOT NULL,
  source_type VARCHAR(64) NOT NULL,
  confidence VARCHAR(32) NOT NULL DEFAULT 'demo',
  limitation_notes TEXT NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_route_code_direction (code, direction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stops (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  locality VARCHAR(120) NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  location POINT NOT NULL,
  description TEXT NULL,
  facilities TEXT NULL,
  accessibility_notes TEXT NULL,
  source_name VARCHAR(160) NOT NULL,
  source_type VARCHAR(64) NOT NULL,
  confidence VARCHAR(32) NOT NULL DEFAULT 'demo',
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  SPATIAL INDEX idx_stops_location (location),
  INDEX idx_stops_name (name),
  INDEX idx_stops_locality (locality)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS places (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  kind VARCHAR(40) NOT NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_places_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS route_stops (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  route_id INT UNSIGNED NOT NULL,
  stop_id INT UNSIGNED NOT NULL,
  sequence_no INT UNSIGNED NOT NULL,
  direction VARCHAR(80) NOT NULL,
  distance_from_prev_m INT UNSIGNED NULL,
  UNIQUE KEY uq_route_seq (route_id, sequence_no),
  UNIQUE KEY uq_route_stop (route_id, stop_id),
  CONSTRAINT fk_rs_route FOREIGN KEY (route_id) REFERENCES routes (id) ON DELETE CASCADE,
  CONSTRAINT fk_rs_stop FOREIGN KEY (stop_id) REFERENCES stops (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS route_geometries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  route_id INT UNSIGNED NOT NULL,
  format VARCHAR(32) NOT NULL DEFAULT 'geojson',
  geometry_json MEDIUMTEXT NOT NULL,
  source_name VARCHAR(160) NOT NULL,
  verified_at DATETIME NULL,
  UNIQUE KEY uq_geom_route (route_id),
  CONSTRAINT fk_geom_route FOREIGN KEY (route_id) REFERENCES routes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fare_estimates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  route_id INT UNSIGNED NOT NULL,
  amount_ghs DECIMAL(8,2) NOT NULL,
  estimate_type VARCHAR(40) NOT NULL,
  source_name VARCHAR(160) NOT NULL,
  effective_date DATE NOT NULL,
  confidence VARCHAR(32) NOT NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_fare_route FOREIGN KEY (route_id) REFERENCES routes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_categories (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  description TEXT NOT NULL,
  route_impact_rule JSON NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_ref VARCHAR(24) NOT NULL,
  reporter_id INT UNSIGNED NOT NULL,
  category_id SMALLINT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  location POINT NOT NULL,
  place_label VARCHAR(160) NULL,
  observed_at DATETIME NOT NULL,
  status ENUM('pending_review','needs_more_information','under_review','verified','resolved','duplicate','rejected') NOT NULL DEFAULT 'pending_review',
  severity ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  related_route_id INT UNSIGNED NULL,
  related_stop_id INT UNSIGNED NULL,
  duplicate_of_id INT UNSIGNED NULL,
  authoritative_closure TINYINT(1) NOT NULL DEFAULT 0,
  closure_source VARCHAR(255) NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_reports_ref (public_ref),
  INDEX idx_reports_status_cat_date (status, category_id, created_at),
  INDEX idx_reports_reporter (reporter_id, created_at),
  SPATIAL INDEX idx_reports_location (location),
  CONSTRAINT fk_reports_user FOREIGN KEY (reporter_id) REFERENCES users (id),
  CONSTRAINT fk_reports_cat FOREIGN KEY (category_id) REFERENCES report_categories (id),
  CONSTRAINT fk_reports_route FOREIGN KEY (related_route_id) REFERENCES routes (id) ON DELETE SET NULL,
  CONSTRAINT fk_reports_stop FOREIGN KEY (related_stop_id) REFERENCES stops (id) ON DELETE SET NULL,
  CONSTRAINT fk_reports_dup FOREIGN KEY (duplicate_of_id) REFERENCES reports (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_media (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id INT UNSIGNED NOT NULL,
  storage_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(80) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  original_name VARCHAR(160) NULL,
  uploaded_at DATETIME NOT NULL,
  CONSTRAINT fk_media_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_status_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id INT UNSIGNED NOT NULL,
  actor_id INT UNSIGNED NULL,
  old_status VARCHAR(40) NULL,
  new_status VARCHAR(40) NOT NULL,
  reason VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_hist_report (report_id, created_at),
  CONSTRAINT fk_hist_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
  CONSTRAINT fk_hist_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id INT UNSIGNED NOT NULL,
  author_id INT UNSIGNED NOT NULL,
  note TEXT NOT NULL,
  visibility ENUM('internal','reporter') NOT NULL DEFAULT 'internal',
  created_at DATETIME NOT NULL,
  INDEX idx_notes_report (report_id, created_at),
  CONSTRAINT fk_notes_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
  CONSTRAINT fk_notes_author FOREIGN KEY (author_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_flags (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  reason VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_flags_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
  CONSTRAINT fk_flags_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affected_routes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id INT UNSIGNED NOT NULL,
  route_id INT UNSIGNED NOT NULL,
  impact_level ENUM('information','possible','likely','confirmed_closure') NOT NULL,
  distance_m INT UNSIGNED NOT NULL,
  reason VARCHAR(500) NOT NULL,
  calculated_at DATETIME NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_aff_route_active (route_id, active),
  INDEX idx_aff_report (report_id, active),
  CONSTRAINT fk_aff_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
  CONSTRAINT fk_aff_route FOREIGN KEY (route_id) REFERENCES routes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_routes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  route_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_saved_route (user_id, route_id),
  CONSTRAINT fk_sr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_sr_route FOREIGN KEY (route_id) REFERENCES routes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_stops (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  stop_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_saved_stop (user_id, stop_id),
  CONSTRAINT fk_ss_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ss_stop FOREIGN KEY (stop_id) REFERENCES stops (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(40) NOT NULL,
  title VARCHAR(160) NOT NULL,
  body VARCHAR(500) NOT NULL,
  related_report_id INT UNSIGNED NULL,
  related_route_id INT UNSIGNED NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_notif_user (user_id, read_at, created_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_report FOREIGN KEY (related_report_id) REFERENCES reports (id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_route FOREIGN KEY (related_route_id) REFERENCES routes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(40) NOT NULL,
  entity_id INT UNSIGNED NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_audit_created (created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(64) NOT NULL,
  email VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_login_window (ip, email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS submission_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_sub_window (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
