<?php
// SMTP config for PHPMailer. All values from environment.
return [
    'host'       => getenv('MAIL_HOST') ?: 'localhost',
    'port'       => (int)(getenv('MAIL_PORT') ?: 587),
    'username'   => getenv('MAIL_USERNAME') ?: '',
    'password'   => getenv('MAIL_PASSWORD') ?: '',
    'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
    'from_email' => getenv('MAIL_FROM') ?: 'no-reply@college.edu',
    'from_name'  => getenv('MAIL_FROM_NAME') ?: 'College SIS',
];
