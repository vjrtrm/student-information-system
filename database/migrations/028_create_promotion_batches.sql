CREATE TABLE promotion_batches (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    department_id           INT UNSIGNED NOT NULL,
    target_academic_year_id INT UNSIGNED NOT NULL,
    target_class_id         INT UNSIGNED NOT NULL,
    target_section_id       INT UNSIGNED NOT NULL,
    status                  ENUM('pending_approval','approved','rejected') NOT NULL DEFAULT 'pending_approval',
    requires_inst_admin     TINYINT(1) NOT NULL DEFAULT 0,
    initiated_by            INT UNSIGNED NOT NULL,
    rejection_reason        TEXT NULL,
    reviewed_by             INT UNSIGNED NULL,
    reviewed_at             DATETIME NULL,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_dept_status (department_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
