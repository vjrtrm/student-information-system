-- Module 4: Enrolment number generation batches (container only — no status column)
CREATE TABLE IF NOT EXISTS enrolment_batches (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  department_id    INT NOT NULL,
  academic_year_id INT NOT NULL,
  generated_by     INT NOT NULL,
  student_count    INT NOT NULL DEFAULT 0,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_enrolment_batches_dept_ay (department_id, academic_year_id),
  CONSTRAINT fk_enrolment_batches_dept    FOREIGN KEY (department_id)    REFERENCES departments(id),
  CONSTRAINT fk_enrolment_batches_ay      FOREIGN KEY (academic_year_id) REFERENCES option_values(id),
  CONSTRAINT fk_enrolment_batches_user    FOREIGN KEY (generated_by)     REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
