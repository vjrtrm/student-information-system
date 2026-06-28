CREATE TABLE settings (
    `key`   VARCHAR(100) NOT NULL,
    value   TEXT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (`key`, value) VALUES ('promotion_window_open', '0');
