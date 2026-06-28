CREATE TABLE notification_error_log (
    id                    INT NOT NULL AUTO_INCREMENT,
    notification_event_id INT NOT NULL,
    error_message         TEXT NOT NULL,
    attempted_at          DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_event (notification_event_id),
    CONSTRAINT fk_nel_event FOREIGN KEY (notification_event_id)
        REFERENCES notification_events(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
