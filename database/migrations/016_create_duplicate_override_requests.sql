-- Module 3: duplicate resolution workflow
CREATE TABLE IF NOT EXISTS duplicate_override_requests (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  upload_batch_id     INT NULL,
  source_row_number   INT NULL,
  student_data        JSON NOT NULL,
  flagged_reason      ENUM('mobile_exists','name_dob_exists','both') NOT NULL,
  existing_student_id INT NOT NULL,
  requested_by        INT NOT NULL,
  reason_note         TEXT NOT NULL,
  status              ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by         INT NULL,
  reviewed_at         DATETIME NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_dup_override_status (status),
  KEY idx_dup_override_batch  (upload_batch_id),
  CONSTRAINT fk_dup_override_batch     FOREIGN KEY (upload_batch_id)     REFERENCES upload_batches(id),
  CONSTRAINT fk_dup_override_existing  FOREIGN KEY (existing_student_id) REFERENCES students(id),
  CONSTRAINT fk_dup_override_requester FOREIGN KEY (requested_by)        REFERENCES users(id),
  CONSTRAINT fk_dup_override_reviewer  FOREIGN KEY (reviewed_by)         REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
