CREATE TABLE promotion_batch_students (
    id         INT NOT NULL AUTO_INCREMENT,
    batch_id   INT UNSIGNED NOT NULL,
    student_id INT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_batch_student (batch_id, student_id),
    KEY idx_batch (batch_id),
    CONSTRAINT fk_pbs_batch   FOREIGN KEY (batch_id)   REFERENCES promotion_batches(id),
    CONSTRAINT fk_pbs_student FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
