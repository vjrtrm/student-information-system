CREATE TABLE promotion_exclusions (
    id         INT NOT NULL AUTO_INCREMENT,
    batch_id   INT UNSIGNED NOT NULL,
    student_id INT NOT NULL,
    reason     TEXT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_excl (batch_id, student_id),
    CONSTRAINT fk_pe_batch   FOREIGN KEY (batch_id)   REFERENCES promotion_batches(id),
    CONSTRAINT fk_pe_student FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
