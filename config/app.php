<?php
// Application config. Tunables read here with sane defaults; secrets come from env.
return [
    'name'     => getenv('APP_NAME') ?: 'Student Information System',
    'env'      => getenv('APP_ENV') ?: 'production',
    'base_url' => rtrim(getenv('APP_BASE_URL') ?: '', '/'),
    'debug'    => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),

    // Authentication tunables (Design §9)
    'auth' => [
        'student_otp_enabled'     => filter_var(getenv('AUTH_STUDENT_OTP') ?: 'false', FILTER_VALIDATE_BOOL),
        'session_timeout_minutes' => (int)(getenv('AUTH_SESSION_TIMEOUT') ?: 30),
        'lockout_threshold'       => (int)(getenv('AUTH_LOCKOUT_THRESHOLD') ?: 5),
        'lockout_minutes'         => (int)(getenv('AUTH_LOCKOUT_MINUTES') ?: 15),
        'otp_ttl_minutes'         => (int)(getenv('AUTH_OTP_TTL') ?: 15),
        'reset_ttl_minutes'       => (int)(getenv('AUTH_RESET_TTL') ?: 15),
        'password_min_length'     => (int)(getenv('AUTH_PWD_MIN') ?: 8),
    ],
];
