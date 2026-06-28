-- Module 4: Add enrolment number columns to students
ALTER TABLE students
  ADD COLUMN enrolment_number          VARCHAR(20)                    NULL AFTER upload_batch_id,
  ADD COLUMN enrolment_serial          SMALLINT UNSIGNED              NULL AFTER enrolment_number,
  ADD COLUMN enrolment_approval_status ENUM('pending','approved')     NULL AFTER enrolment_serial,
  ADD COLUMN enrolment_batch_id        INT                            NULL AFTER enrolment_approval_status,
  ADD COLUMN enrolment_approved_by     INT                            NULL AFTER enrolment_batch_id,
  ADD COLUMN enrolment_approved_at     DATETIME                       NULL AFTER enrolment_approved_by;

-- Sparse unique index: MySQL 5.7 allows multiple NULLs in a unique index
ALTER TABLE students
  ADD UNIQUE KEY uq_students_enrolment_number   (enrolment_number),
  ADD KEY        idx_students_enrolment_batch    (enrolment_batch_id),
  ADD KEY        idx_students_enrolment_status   (enrolment_approval_status);

ALTER TABLE students
  ADD CONSTRAINT fk_students_enrolment_batch    FOREIGN KEY (enrolment_batch_id)    REFERENCES enrolment_batches(id),
  ADD CONSTRAINT fk_students_enrolment_approved FOREIGN KEY (enrolment_approved_by) REFERENCES users(id);
