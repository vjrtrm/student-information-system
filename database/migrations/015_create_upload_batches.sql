-- Module 3: bulk upload tracking
CREATE TABLE IF NOT EXISTS upload_batches (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  department_id        INT NOT NULL,
  uploaded_by          INT NOT NULL,
  original_filename    VARCHAR(255) NULL,
  total_rows           INT NOT NULL DEFAULT 0,
  created_count        INT NOT NULL DEFAULT 0,
  duplicate_held_count INT NOT NULL DEFAULT 0,
  failed_count         INT NOT NULL DEFAULT 0,
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_upload_batches_dept (department_id),
  CONSTRAINT fk_upload_batches_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_upload_batches_user FOREIGN KEY (uploaded_by)   REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
