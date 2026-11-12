<?php
declare(strict_types=1);

$name = (string)getenv('CC_TEST_DB_NAME');
if (preg_match('/^campusconnect_test(?:_[a-z0-9_]+)?$/i', $name) !== 1) {
    throw new RuntimeException('CC_TEST_DB_NAME must identify a dedicated campusconnect_test database.');
}
foreach (['HOST', 'PORT', 'NAME', 'USER', 'PASSWORD'] as $setting) {
    $value = getenv('CC_TEST_DB_' . $setting);
    if ($value === false || $value === '') {
        throw new RuntimeException('Missing CC_TEST_DB_' . $setting);
    }
    putenv('CC_DB_' . $setting . '=' . $value);
}
$db = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('CC_DB_HOST'), getenv('CC_DB_PORT'), $name),
    (string)getenv('CC_DB_USER'),
    (string)getenv('CC_DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

function runProvisioning(array $emails, string $input): array
{
    $command = array_merge([PHP_BINARY, dirname(__DIR__) . '/app/cli/provision-admins.php'], $emails);
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start provisioning command.');
    }
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $output, $error];
}

function expectProvision(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$prefix = 'provision-test-' . bin2hex(random_bytes(6));
$first = $prefix . '-first@example.edu';
$second = $prefix . '-second@example.edu';
$third = $prefix . '-third@example.edu';
$password = 'temporary-test-password';
$check = $db->prepare('SELECT COUNT(*) FROM superadmin_users WHERE email IN (?, ?, ?)');
$check->execute([$first, $second, $third]);
if ((int)$check->fetchColumn() !== 0) {
    throw new RuntimeException('Test email collision; no accounts were changed.');
}
$created = false;
try {
    [$exit, $output, $error] = runProvisioning([$first, $second], "$password\n$password\n");
    $created = $exit === 0;
    expectProvision($exit === 0 && str_contains($output, '2 Admin account(s) created'), 'Creating accounts failed: ' . $error);
    expectProvision(!str_contains($output . $error, $password), 'The command printed the password.');
    $lookup = $db->prepare('SELECT password_hash FROM superadmin_users WHERE email = ?');
    $lookup->execute([$first]);
    $firstHash = $lookup->fetchColumn();
    $lookup->execute([$second]);
    $secondHash = $lookup->fetchColumn();
    expectProvision(is_string($firstHash) && is_string($secondHash), 'Created accounts are missing.');
    expectProvision(password_verify($password, $firstHash) && password_verify($password, $secondHash), 'Stored credentials do not verify.');
    expectProvision($firstHash !== $secondHash, 'Passwords must use separate salts.');

    [$exit] = runProvisioning([$first, $third], "different-password\ndifferent-password\n");
    expectProvision($exit !== 0, 'Existing Admin account was overwritten.');
    $lookup->execute([$first]);
    expectProvision($lookup->fetchColumn() === $firstHash, 'Existing Admin password changed.');
    $lookup->execute([$third]);
    expectProvision($lookup->fetchColumn() === false, 'A partial batch was created.');

    [$exit] = runProvisioning([$third], "password-one\npassword-two\n");
    expectProvision($exit !== 0, 'Mismatched passwords were accepted.');
    $lookup->execute([$third]);
    expectProvision($lookup->fetchColumn() === false, 'Invalid input created an account.');
    echo "Admin provisioning tests passed.\n";
} finally {
    if ($created) {
        $db->prepare('DELETE FROM superadmin_users WHERE email IN (?, ?, ?)')->execute([$first, $second, $third]);
    }
}