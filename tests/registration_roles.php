<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Models/UserModel.php';

final class RegistrationStatement extends PDOStatement
{
    public function __construct(private RegistrationDatabase $database)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->database->inserted = $params;
        return true;
    }
}

final class RegistrationDatabase extends PDO
{
    public ?array $inserted = null;

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (!str_contains($query, 'INSERT INTO admin_users')) {
            throw new RuntimeException('Unexpected registration query.');
        }
        return new RegistrationStatement($this);
    }
}

$data = [
    'firstName' => 'Student',
    'lastName' => 'Moderator',
    'email' => 'student@example.edu',
    'password' => 'test-password',
    'confirm' => 'test-password',
];
$db = new RegistrationDatabase();
$users = new UserModel($db);

foreach ([$data, $data + ['role' => 'moderator']] as $registration) {
    $db->inserted = null;
    if ($users->register($registration) !== [] || ($db->inserted[3] ?? null) !== 'moderator') {
        throw new RuntimeException('Public registration must create a moderator account.');
    }
}

foreach (['admin', '', ['admin']] as $role) {
    $db->inserted = null;
    $result = $users->register($data + ['role' => $role]);
    if (!isset($result['error']) || $db->inserted !== null) {
        throw new RuntimeException('Public registration must reject invalid or admin roles.');
    }
}

echo "Registration role tests passed.\n";
