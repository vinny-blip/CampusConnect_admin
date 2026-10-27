<?php
declare(strict_types=1);

// Load a private project .env file when the server has not supplied a value.
$envFile = dirname(__DIR__) . '/.env';

$localEnvironment = [];
if (is_file($envFile)) {
    $parsedEnvironment = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if ($parsedEnvironment === false) {
        throw new RuntimeException('The project .env file could not be parsed.');
    }
    $localEnvironment = $parsedEnvironment;
}

// Check common PHP environment sources before falling back to the local .env file.
$readEnvironment = static function (string $name) use ($localEnvironment): string {
    $value = getenv($name);
    if ($value !== false && trim($value) !== '') {
        return trim($value);
    }

    foreach ([$_SERVER[$name] ?? null, $_ENV[$name] ?? null] as $value) {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    $value = $localEnvironment[$name] ?? '';
    return is_string($value) ? trim($value) : '';
};

$config = [
    'db_host' => $readEnvironment('CC_DB_HOST'),
    'db_port' => $readEnvironment('CC_DB_PORT'),
    'db_name' => $readEnvironment('CC_DB_NAME'),
    'db_user' => $readEnvironment('CC_DB_USER'),
    'db_password' => $readEnvironment('CC_DB_PASSWORD'),
];

$environmentNames = [
    'db_host' => 'CC_DB_HOST',
    'db_port' => 'CC_DB_PORT',
    'db_name' => 'CC_DB_NAME',
    'db_user' => 'CC_DB_USER',
    'db_password' => 'CC_DB_PASSWORD',
];
foreach ($config as $key => $value) {
    if ($value === '') {
        throw new RuntimeException(
            "Database setting {$environmentNames[$key]} is missing. Set it in the hosting environment or create a private .env file beside the project .htaccess."
        );
    }
}

return $config;
