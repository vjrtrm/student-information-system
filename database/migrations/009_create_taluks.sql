CREATE TABLE IF NOT EXISTS taluks (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  district_id INT NOT NULL,
  name        VARCHAR(100) NOT NULL,
  status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_taluks_district_name (district_id, name),
  KEY idx_taluks_district (district_id),
  CONSTRAINT fk_taluks_district FOREIGN KEY (district_id) REFERENCES districts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
