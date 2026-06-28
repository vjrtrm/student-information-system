-- Module 3: expand students with onboarding fields
-- NOTE: MySQL 5.7 does not support IF NOT EXISTS on ALTER TABLE.
-- The migration runner skips on error if a column already exists.

ALTER TABLE students
  ADD COLUMN first_name        VARCHAR(100)  NULL AFTER id,
  ADD COLUMN last_name         VARCHAR(100)  NULL AFTER first_name,
  ADD COLUMN gender            ENUM('male','female','other') NULL AFTER dob,
  ADD COLUMN programme_level   ENUM('UG','PG') NULL AFTER department_id,
  ADD COLUMN academic_year_id  INT NULL AFTER programme_level,
  ADD COLUMN class_id          INT NULL AFTER academic_year_id,
  ADD COLUMN section_id        INT NULL AFTER class_id,
  ADD COLUMN admission_date    DATE NULL AFTER section_id,
  ADD COLUMN onboarding_status ENUM('pending_enrolment','enrolment_assigned','form_submitted','approved') NOT NULL DEFAULT 'pending_enrolment' AFTER status,
  ADD COLUMN login_enabled     TINYINT(1) NOT NULL DEFAULT 0 AFTER onboarding_status,
  ADD COLUMN created_by        INT NULL AFTER login_enabled,
  ADD COLUMN upload_batch_id   INT NULL AFTER created_by,
  ADD COLUMN updated_at        TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE students
  ADD KEY idx_students_name_dob          (first_name, last_name, dob),
  ADD KEY idx_students_onboarding_status (onboarding_status),
  ADD KEY idx_students_academic_year     (academic_year_id);

ALTER TABLE students
  ADD CONSTRAINT fk_students_created_by   FOREIGN KEY (created_by)      REFERENCES users(id),
  ADD CONSTRAINT fk_students_academic_year FOREIGN KEY (academic_year_id) REFERENCES option_values(id),
  ADD CONSTRAINT fk_students_class        FOREIGN KEY (class_id)         REFERENCES option_values(id),
  ADD CONSTRAINT fk_students_section      FOREIGN KEY (section_id)       REFERENCES option_values(id);
