-- Auth-relevant subset of students (full record defined in Module 5).
CREATE TABLE IF NOT EXISTS students (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  mobile          VARCHAR(10) NOT NULL,
  dob             DATE NOT NULL,
  department_id   INT NULL,
  status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
  failed_attempts INT NOT NULL DEFAULT 0,
  locked_until    DATETIME NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_students_mobile (mobile),
  KEY idx_students_department (department_id),
  CONSTRAINT fk_students_department FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
