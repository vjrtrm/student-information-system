-- One-time login codes (used only when student OTP is enabled). Single-use, time-boxed.
CREATE TABLE IF NOT EXISTS login_otps (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  principal_type ENUM('student','user') NOT NULL,
  principal_id   INT NOT NULL,
  code_hash      VARCHAR(255) NOT NULL,
  expires_at     DATETIME NOT NULL,
  used_at        DATETIME NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_otps_principal (principal_type, principal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
