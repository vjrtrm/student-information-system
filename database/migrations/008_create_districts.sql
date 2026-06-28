CREATE TABLE IF NOT EXISTS districts (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  state_id   INT NOT NULL,
  name       VARCHAR(100) NOT NULL,
  status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_districts_state_name (state_id, name),
  KEY idx_districts_state (state_id),
  CONSTRAINT fk_districts_state FOREIGN KEY (state_id) REFERENCES states(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
