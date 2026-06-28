CREATE TABLE notification_events (
    id                INT NOT NULL AUTO_INCREMENT,
    event_key         VARCHAR(50) NOT NULL,
    student_id        INT NOT NULL,
    actor_id          INT NOT NULL,
    recipient_type    ENUM('student','dept_admin') NOT NULL,
    recipient_id      INT NULL,
    change_request_id INT NULL,
    payload           JSON NOT NULL,
    sent_at           DATETIME NULL,
    created_at        DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_unsent (sent_at),
    CONSTRAINT fk_ne_student FOREIGN KEY (student_id)        REFERENCES students(id),
    CONSTRAINT fk_ne_actor   FOREIGN KEY (actor_id)          REFERENCES users(id),
    CONSTRAINT fk_ne_cr      FOREIGN KEY (change_request_id) REFERENCES change_requests(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
