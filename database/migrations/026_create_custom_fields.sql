CREATE TABLE custom_fields (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    field_key     VARCHAR(20)  NOT NULL DEFAULT '',
    label         VARCHAR(150) NOT NULL,
    field_type    ENUM('text','textarea','number','date','select') NOT NULL,
    section       VARCHAR(60)  NOT NULL,
    scope         ENUM('institution','department') NOT NULL DEFAULT 'institution',
    department_id INT UNSIGNED NULL,
    mode          ENUM('required','optional','hidden') NOT NULL DEFAULT 'optional',
    options       JSON NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_by    INT UNSIGNED NOT NULL,
    created_at    DATETIME NOT NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_key (field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
