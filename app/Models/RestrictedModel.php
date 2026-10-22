<?php
declare(strict_types=1);

final class RestrictedModel
{
    private string $adminEmail;

    public function __construct(private PDO $db)
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $this->adminEmail = mb_strtolower(trim((string)$config['admin_email']));
    }

    public function list(): array
    {
        $statement = $this->db->query(
            'SELECT restrictions.user_id, users.name, users.email, restrictions.reason, restrictions.created_at AS t
             FROM admin_user_restrictions AS restrictions
             INNER JOIN admin_users AS users ON users.id = restrictions.user_id
             ORDER BY restrictions.created_at DESC'
        );
        return $statement->fetchAll();
    }

    public function isRestricted(int $userId): bool
    {
        $statement = $this->db->prepare('SELECT 1 FROM admin_user_restrictions WHERE user_id = ?');
        $statement->execute([$userId]);
        return $statement->fetchColumn() !== false;
    }

    public function add(string $email, string $reason, int $adminId): ?string
    {
        $email = mb_strtolower(trim($email));
        $reason = trim($reason);
        if ($email === '') {
            return 'Enter the moderator account email to restrict.';
        }
        if ($reason === '') {
            return 'Add a reason so other moderators know why.';
        }
        if ($email === $this->adminEmail) {
            return 'The admin account cannot be restricted here.';
        }

        $target = $this->db->prepare('SELECT id FROM admin_users WHERE LOWER(email) = ?');
        $target->execute([$email]);
        $userId = $target->fetchColumn();
        if ($userId === false) {
            return 'No moderator account was found with that email.';
        }
        if ($this->isRestricted((int)$userId)) {
            return 'That moderator is already restricted.';
        }

        $statement = $this->db->prepare(
            'INSERT INTO admin_user_restrictions (user_id, restricted_by, reason, created_at) VALUES (?, ?, ?, ?)'
        );
        $statement->execute([(int)$userId, $adminId, $reason, (int)(microtime(true) * 1000)]);
        return null;
    }

    public function remove(int $userId): void
    {
        $statement = $this->db->prepare('DELETE FROM admin_user_restrictions WHERE user_id = ?');
        $statement->execute([$userId]);
    }
}
