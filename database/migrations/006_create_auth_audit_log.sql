-- Authentication audit trail (Design §4, §D3). No secrets stored.
CREATE TABLE IF NOT EXISTS auth_audit_log (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  principal_type ENUM('student','user','unknown') NOT NULL DEFAULT 'unknown',
  principal_id   INT NULL,
  event          ENUM('login_success','login_fail','lockout','logout','reset_request','reset_success') NOT NULL,
  ip             VARCHAR(45) NULL,
  user_agent     VARCHAR(255) NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_auth_audit_principal (principal_type, principal_id),
  KEY idx_auth_audit_event (event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
