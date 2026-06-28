CREATE TABLE IF NOT EXISTS option_values (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  list_id      INT NOT NULL,
  value        VARCHAR(150) NOT NULL,
  display      VARCHAR(150) NOT NULL,
  sort_order   INT NOT NULL DEFAULT 0,
  status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_option_values_list_value (list_id, value),
  KEY idx_option_values_list (list_id),
  KEY idx_option_values_sort (list_id, sort_order),
  CONSTRAINT fk_option_values_list FOREIGN KEY (list_id) REFERENCES option_lists(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
