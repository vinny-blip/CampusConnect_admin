<?php
declare(strict_types=1);

final class UserModel
{
    public function __construct(private PDO $db)
    {
    }

    public function findById(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT id, name, email, role, profile_image FROM admin_users WHERE id = ?');
        $statement->execute([$id]);
        $user = $statement->fetch();
        return $user ? $this->withRole($user) : null;
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->db->prepare('SELECT id, name, email, role FROM admin_users WHERE email = ?');
        $statement->execute([mb_strtolower(trim($email))]);
        $user = $statement->fetch();
        return $user ? $this->withRole($user) : null;
    }

    public function login(string $email, string $password): ?array
    {
        $statement = $this->db->prepare('SELECT id, name, email, password_hash FROM admin_users WHERE email = ?');
        $statement->execute([mb_strtolower(trim($email))]);
        $user = $statement->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        return $this->withRole($user);
    }

    public function register(array $data): array
    {
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

    public function updateName(int $id, string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }
        $statement = $this->db->prepare('UPDATE admin_users SET name = ? WHERE id = ?');
        $statement->execute([$name, $id]);
        return true;
    }

    public function updateProfileImage(int $id, string $path): void
    {
        $statement = $this->db->prepare('UPDATE admin_users SET profile_image = ? WHERE id = ?');
        $statement->execute([$path, $id]);
    }

    public function changePassword(int $id, string $currentPassword, string $newPassword): bool
    {
        $statement = $this->db->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
        $statement->execute([$id]);
        $passwordHash = $statement->fetchColumn();
        if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
            return false;
        }

        $statement = $this->db->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
        $statement->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
        return true;
    }

    private function withRole(array $user): array
    {
        $user['id'] = (int)$user['id'];
        $user['role'] = (string)($user['role'] ?? 'moderator');
        unset($user['password_hash']);
        return $user;
    }
}
