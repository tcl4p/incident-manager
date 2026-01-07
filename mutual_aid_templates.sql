-- Mutual Aid Templates (home department -> mutual aid department)
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS department_mutual_aid_command_staff_template (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  home_dept_id INT UNSIGNED NOT NULL,
  mutual_dept_id INT NOT NULL,
  officer_display VARCHAR(100) NOT NULL,   -- e.g. "Chief 50" or "Captain Smith"
  rank_id INT UNSIGNED NOT NULL DEFAULT 0,
  radio_designation VARCHAR(100) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_home (home_dept_id),
  KEY idx_mutual (mutual_dept_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS department_mutual_aid_apparatus_template (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  home_dept_id INT UNSIGNED NOT NULL,
  mutual_dept_id INT NOT NULL,
  apparatus_label VARCHAR(100) NOT NULL,   -- e.g. "Eng 56", "Truck 8"
  apparatus_type_id INT UNSIGNED NOT NULL DEFAULT 0, -- optional, links to apparatus_types
  staffing INT NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_home (home_dept_id),
  KEY idx_mutual (mutual_dept_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
