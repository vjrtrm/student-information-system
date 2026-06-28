CREATE TABLE student_custom_data (
    id              INT NOT NULL AUTO_INCREMENT,
    student_id      INT NOT NULL,
    custom_field_id INT UNSIGNED NOT NULL,
    value           TEXT NOT NULL,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_field (student_id, custom_field_id),
    KEY idx_student (student_id),
    CONSTRAINT fk_scd_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_scd_field   FOREIGN KEY (custom_field_id) REFERENCES custom_fields(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
