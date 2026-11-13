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

    /** Verifies a permanent or unexpired temporary password for the selected role. */
    public function login(string $email, string $password, string $role): ?array
    {
        if (!in_array($role, ['admin', 'moderator'], true)) {
            return null;
        }
        $table = $this->accountTable($role);
        $sql = $role === 'admin'
            ? "SELECT id, name, email, password_hash, temp_password_hash, temp_password_expires_at, temp_password_attempts, 'admin' AS role FROM {$table} WHERE email = ?"
            : "SELECT id, name, email, password_hash, temp_password_hash, temp_password_expires_at, temp_password_attempts, role FROM {$table} WHERE email = ? AND role = 'moderator' AND suspended = 0";
        $statement = $this->db->prepare($sql);
        $statement->execute([mb_strtolower(trim($email))]);
        $user = $statement->fetch();
        if (!$user) {
            return null;
        }

        $permanentPasswordMatches = password_verify($password, $user['password_hash']);
        $temporaryPasswordMatches = $this->temporaryPasswordMatches($password, $user);
        if (!$permanentPasswordMatches && !$temporaryPasswordMatches) {
            $this->incrementTemporaryPasswordAttempts($table, (int)$user['id'], $user);
            return null;
        }
        if ($permanentPasswordMatches) {
            $this->clearTemporaryPassword($table, (int)$user['id']);
        }

        $user = $this->withRole($user);
        $user['temporary_password_login'] = $temporaryPasswordMatches;
        return $user;
    }

    /** Emails a short-lived temporary password without revealing whether an account exists. */
    public function sendTemporaryPassword(string $email, string $role): void
    {
        if (!in_array($role, ['admin', 'moderator'], true)) {
            throw new InvalidArgumentException('Invalid dashboard role.');
        }
        $table = $this->accountTable($role);
        $sql = $role === 'admin'
            ? "SELECT id, email, temp_password_sent_at FROM {$table} WHERE email = ?"
            : "SELECT id, email, temp_password_sent_at FROM {$table} WHERE email = ? AND role = 'moderator' AND suspended = 0";
        $statement = $this->db->prepare($sql);
        $statement->execute([mb_strtolower(trim($email))]);
        $account = $statement->fetch();
        if (!$account) {
            return;
        }

        $sentAt = $account['temp_password_sent_at'] ?? null;
        $lastSent = is_string($sentAt) ? strtotime($sentAt . ' UTC') : false;
        if ($lastSent !== false && $lastSent > time() - 60) {
            return;
        }

        $temporaryPassword = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 30 * 60);
        $sentAt = gmdate('Y-m-d H:i:s');
        $statement = $this->db->prepare(
            "UPDATE {$table}
             SET temp_password_hash = ?, temp_password_expires_at = ?, temp_password_sent_at = ?, temp_password_attempts = 0
             WHERE id = ?
               AND (temp_password_sent_at IS NULL
                    OR temp_password_sent_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE))"
        );
        $statement->execute([$passwordHash, $expiresAt, $sentAt, (int)$account['id']]);
        if ($statement->rowCount() !== 1) {
            return;
        }

        $subject = 'CampusConnect temporary password (valid for 30 minutes)';
        $message = "Your temporary password is: {$temporaryPassword}\n\n"
            . "It is valid for 30 minutes. Log in using the selected account role, then change your password in Profile.";
        $headers = "From: noreply@campusconnect.uslsbsit.com\r\nContent-Type: text/plain; charset=UTF-8";
        if (!filter_var((string)$account['email'], FILTER_VALIDATE_EMAIL)
            || !mail((string)$account['email'], $subject, $message, $headers)) {
            $this->db->prepare(
                "UPDATE {$table} SET temp_password_hash = NULL, temp_password_expires_at = NULL, temp_password_attempts = 0
                 WHERE id = ? AND temp_password_hash = ?"
            )->execute([(int)$account['id'], $passwordHash]);
            error_log('[CampusConnect password recovery] Temporary password email could not be sent.');
        }
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
        $statement = $this->db->prepare(
            "SELECT password_hash, temp_password_hash, temp_password_expires_at, temp_password_attempts
             FROM {$table} WHERE id = ?"
        );
        $statement->execute([$id]);
        $account = $statement->fetch();
        if (!$account || (
            !password_verify($currentPassword, $account['password_hash'])
            && !$this->temporaryPasswordMatches($currentPassword, $account)
        )) {
            return false;
        }

        $statement = $this->db->prepare(
            "UPDATE {$table}
             SET password_hash = ?, temp_password_hash = NULL, temp_password_expires_at = NULL,
                 temp_password_sent_at = NULL, temp_password_attempts = 0
             WHERE id = ?"
        );
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

    private function temporaryPasswordMatches(string $password, array $account): bool
    {
        $expiry = $account['temp_password_expires_at'] ?? null;
        $expiresAt = is_string($expiry) ? strtotime($expiry . ' UTC') : false;
        return is_string($account['temp_password_hash'] ?? null)
            && $account['temp_password_hash'] !== ''
            && $expiresAt !== false
            && $expiresAt >= time()
            && (int)($account['temp_password_attempts'] ?? 0) < 5
            && password_verify($password, $account['temp_password_hash']);
    }

    private function incrementTemporaryPasswordAttempts(string $table, int $id, array $account): void
    {
        if (!empty($account['temp_password_hash']) && $this->temporaryPasswordIsUnexpired($account)) {
            $this->db->prepare(
                "UPDATE {$table} SET temp_password_attempts = LEAST(temp_password_attempts + 1, 5)
                 WHERE id = ? AND temp_password_expires_at >= UTC_TIMESTAMP()"
            )->execute([$id]);
        }
    }

    private function temporaryPasswordIsUnexpired(array $account): bool
    {
        $expiry = $account['temp_password_expires_at'] ?? null;
        $expiresAt = is_string($expiry) ? strtotime($expiry . ' UTC') : false;
        return $expiresAt !== false && $expiresAt >= time();
    }

    private function clearTemporaryPassword(string $table, int $id): void
    {
        $this->db->prepare(
            "UPDATE {$table}
             SET temp_password_hash = NULL, temp_password_expires_at = NULL, temp_password_attempts = 0
             WHERE id = ?"
        )->execute([$id]);
    }

    /** Normalizes account fields and removes the password hash from results. */
    private function withRole(array $user): array
    {
        $user['id'] = (int)$user['id'];
        $user['role'] = (string)($user['role'] ?? 'moderator');
        unset(
            $user['password_hash'],
            $user['temp_password_hash'],
            $user['temp_password_expires_at'],
            $user['temp_password_sent_at'],
            $user['temp_password_attempts']
        );
        return $user;
    }
}
