<?php
declare(strict_types=1);

final class ModerationActivityModel
{
    public function __construct(private PDO $db)
    {
    }

    public function record(array $actor, string $action, string $targetType, int $targetId, string $reason = '', ?string $targetLabel = null): void
    {
        $this->db->prepare(
            'INSERT INTO superadmin_moderation_activity
             (actor_id, actor_role, actor_name, actor_email, action, target_type, target_id, reason, target_label)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int)$actor['id'], $actor['role'], $actor['name'], $actor['email'],
            $action, $targetType, $targetId, $reason === '' ? null : $reason, $targetLabel,
        ]);
    }

    public function recent(): array
    {
        return $this->db->query(
            'SELECT actor_name, actor_email, actor_role, action, target_type, target_id, target_label, reason, created_at
             FROM superadmin_moderation_activity ORDER BY created_at DESC, id DESC LIMIT 200'
        )->fetchAll();
    }
}
