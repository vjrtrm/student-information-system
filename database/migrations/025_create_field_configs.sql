CREATE TABLE field_configs (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    field_key     VARCHAR(60)  NOT NULL,
    department_id INT UNSIGNED NOT NULL DEFAULT 0,
    mode          ENUM('required','optional','hidden') NOT NULL,
    created_at    DATETIME NOT NULL,
    updated_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_dept (field_key, department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
