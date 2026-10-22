<?php
declare(strict_types=1);

// Hostinger does not expose Apache environment variables by default. Create
// config/database.local.php on the server to override these values.
$config = [
    'db_host' => getenv('CC_DB_HOST') ?: '127.0.0.1',
    'db_port' => getenv('CC_DB_PORT') ?: '3306',
    'db_name' => getenv('CC_DB_NAME') ?: 'campusconnect',
    'db_user' => getenv('CC_DB_USER') ?: 'root',
    'db_password' => getenv('CC_DB_PASSWORD') ?: '',
    'admin_email' => getenv('CC_ADMIN_EMAIL') ?: 's2300416@usls.edu.ph',
];

$localConfig = __DIR__ . '/database.local.php';
if (is_file($localConfig)) {
    $overrides = require $localConfig;
    if (!is_array($overrides)) {
        throw new RuntimeException('config/database.local.php must return an array.');
    }
    $config = array_replace($config, $overrides);
}

return $config;
