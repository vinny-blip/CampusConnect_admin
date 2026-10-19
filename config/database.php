<?php
declare(strict_types=1);

return [
    'db_host' => getenv('CC_DB_HOST') ?: '127.0.0.1',
    'db_port' => getenv('CC_DB_PORT') ?: '3306',
    'db_name' => getenv('CC_DB_NAME') ?: 'campusconnect',
    'db_user' => getenv('CC_DB_USER') ?: 'root',
    'db_password' => getenv('CC_DB_PASSWORD') ?: '',
    'admin_email' => getenv('CC_ADMIN_EMAIL') ?: 's2300416@usls.edu.ph',
];
