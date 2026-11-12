<?php
declare(strict_types=1);

final class UserModel
{
    /** Shares the database connection used for administrator accounts. */
    public function __construct(private PDO $db)
    {
    }

    /** Looks up a dashboard account by its numeric ID. */
    public function findById(int $id, string $role = 'moderator'): ?array
    {
        if (!in_array($role, ['admin', 'moderator'], true)) {
            return null;
        }
        $sql = $role === 'admin'
            ? "SELECT id, name, email, 'admin' AS role, profile_image FROM superadmin_users WHERE id = ?"
            : "SELECT id, name, email, role, profile_image FROM admin_users WHERE id = ? AND role = 'moderator' AND suspended = 0";
        $statement = $this->db->prepare($sql);
        $statement->execute([$id]);
        $user = $statement->fetch();
        return $user ? $this->withRole($user) : null;
    }

    /** Looks up a dashboard account by email address. */
    public function findByEmail(string $email, string $role = 'moderator'): ?array
    {
        $table = $this->accountTable($role);
        $sql = $role === 'admin'
            ? "SELECT id, name, email, 'admin' AS role FROM {$table} WHERE email = ?"
            : "SELECT id, name, email, role FROM {$table} WHERE email = ? AND role = 'moderator'";
        $statement = $this->db->prepare($sql);
        $statement->execute([mb_strtolower(trim($email))]);
        $user = $statement->fetch();
        return $user ? $this->withRole($user) : null;
    }

    /** Verifies the password and selected role against the dashboard account. */
    public function login(string $email, string $password, string $role): ?array
    {
        if (!in_array($role, ['moderator', 'admin'], true)) {
            return null;
        }
        $sql = $role === 'admin'
            ? "SELECT id, name, email, password_hash, 'admin' AS role FROM superadmin_users WHERE email = ?"
            : "SELECT id, name, email, password_hash, role FROM admin_users WHERE email = ? AND role = 'moderator' AND suspended = 0";
        $statement = $this->db->prepare($sql);
        $statement->execute([mb_strtolower(trim($email))]);
        $user = $statement->fetch();
        if (!$user || !password_verify($password, $user['password_hash']) || $user['role'] !== $role) {
            return null;
        }
        return $this->withRole($user);
    }

    /** Validates and creates a moderator account from submitted form data. */
    public function register(array $data): array
    {
        if (array_key_exists('role', $data) && $data['role'] !== 'moderator') {
            return ['error' => 'Only student moderator accounts can be registered.'];
        }

        $first = trim((string)($data['firstName'] ?? ''));
        $last = trim((string)($data['lastName'] ?? ''));
        $email = mb_strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        $confirm = (string)($data['confirm'] ?? '');

        if ($first === '' || $last === '') {
            return ['error' => 'Enter your first and last name.'];
        }
        if (!preg_match('/^[^\s@]+@[^\s@]+\.edu(?:\.[a-z]{2})?$/i', $email)) {
            return ['error' => 'Use your institutional email (ends in .edu or .edu.ph).'];
        }
        if (strlen($password) < 8) {
            return ['error' => 'Password must be at least 8 characters.'];
        }
        if ($password !== $confirm) {
            return ['error' => 'Passwords do not match.'];
        }
        try {
            $statement = $this->db->prepare('INSERT INTO admin_users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $statement->execute(["{$first} {$last}", $email, password_hash($password, PASSWORD_DEFAULT), 'moderator']);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return ['error' => 'That email is already registered. Log in instead.'];
            }
            throw $exception;
        }
        return [];
    }

    /** Updates an account's display name. */
    public function updateName(int $id, string $name, string $role = 'moderator'): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }
        $table = $this->accountTable($role);
        $statement = $this->db->prepare("UPDATE {$table} SET name = ? WHERE id = ?");
        $statement->execute([$name, $id]);
        return true;
    }

    /** Saves the path of an account's profile image. */
    public function updateProfileImage(int $id, string $path, string $role = 'moderator'): void
    {
        $table = $this->accountTable($role);
        $statement = $this->db->prepare("UPDATE {$table} SET profile_image = ? WHERE id = ?");
        $statement->execute([$path, $id]);
    }

    /** Verifies the current password before saving a replacement. */
    public function changePassword(int $id, string $currentPassword, string $newPassword, string $role = 'moderator'): bool
    {
        $table = $this->accountTable($role);
        $statement = $this->db->prepare("SELECT password_hash FROM {$table} WHERE id = ?");
        $statement->execute([$id]);
        $passwordHash = $statement->fetchColumn();
        if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
            return false;
        }

        $statement = $this->db->prepare("UPDATE {$table} SET password_hash = ? WHERE id = ?");
        $statement->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
        return true;
    }

    private function accountTable(string $role): string
    {
        return match ($role) {
            'moderator' => 'admin_users',
            'admin' => 'superadmin_users',
            default => throw new InvalidArgumentException('Invalid dashboard role.'),
        };
    }

    /** Normalizes account fields and removes the password hash from results. */
    private function withRole(array $user): array
    {
        $user['id'] = (int)$user['id'];
        $user['role'] = (string)($user['role'] ?? 'moderator');
        unset($user['password_hash']);
        return $user;
    }
}
