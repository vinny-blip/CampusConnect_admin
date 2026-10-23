<?php
declare(strict_types=1);

$config = [
    // Hostinger's MySQL server is local to the hosted PHP account.
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => 'u793073111_campusconnect',
    'db_user' => 'u793073111_campusconnect',
    'db_password' => getenv('CC_DB_PASSWORD') ?: 'CampusConnect1210713',
];

$required = ['db_host', 'db_port', 'db_name', 'db_user', 'db_password'];
foreach ($required as $key) {
    if (!array_key_exists($key, $config) || trim((string)$config[$key]) === '') {
        if ($key === 'db_password') {
            throw new RuntimeException('Hostinger database password is missing. Set CC_DB_PASSWORD in the server environment.');
        }
        throw new RuntimeException("Hostinger database configuration is missing {$key}.");
    }
}

return $config;
