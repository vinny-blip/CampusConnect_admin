<?php
declare(strict_types=1);

final class ModeratorAccountModel
{
    public function __construct(private PDO $db)
    {
    }

    public function list(): array
    {
        $this->restoreExpiredSuspensions();
        return $this->db->query(
            "SELECT id, name, email, profile_image, suspended, suspended_until, created_at FROM admin_users
             WHERE role = 'moderator' ORDER BY name, id"
        )->fetchAll();
    }

    public function restoreExpiredSuspensions(): int
    {
        return $this->db->exec(
            "UPDATE admin_users SET suspended = 0, suspended_until = NULL
             WHERE role = 'moderator' AND suspended = 1 AND suspended_until IS NOT NULL
               AND suspended_until <= UTC_TIMESTAMP()"
        );
    }

    public function recentSessions(): array
    {
        return $this->db->query(
            'SELECT moderator_name, moderator_email, login_at, logout_at, end_reason
             FROM superadmin_moderator_login_logs ORDER BY login_at DESC, id DESC LIMIT 500'
        )->fetchAll();
    }

    public function startSession(int $moderatorId): ?int
    {
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                "SELECT name, email FROM admin_users WHERE id = ? AND role = 'moderator' AND suspended = 0 FOR UPDATE"
            );
            $statement->execute([$moderatorId]);
            $moderator = $statement->fetch();
            if (!$moderator) {
                $this->db->rollBack();
                return null;
            }
            $this->db->prepare(
                'INSERT INTO superadmin_moderator_login_logs (moderator_id, moderator_name, moderator_email) VALUES (?, ?, ?)'
            )->execute([$moderatorId, $moderator['name'], $moderator['email']]);
            $sessionId = (int)$this->db->lastInsertId();
            $this->db->commit();
            return $sessionId;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function sessionActive(int $sessionId, int $moderatorId): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1 FROM superadmin_moderator_login_logs WHERE id = ? AND moderator_id = ? AND logout_at IS NULL'
        );
        $statement->execute([$sessionId, $moderatorId]);
        return $statement->fetchColumn() !== false;
    }

    public function endSession(int $sessionId, int $moderatorId): void
    {
        $this->db->prepare(
            "UPDATE superadmin_moderator_login_logs SET logout_at = CURRENT_TIMESTAMP, end_reason = 'logout'
             WHERE id = ? AND moderator_id = ? AND logout_at IS NULL"
        )->execute([$sessionId, $moderatorId]);
    }

    public function setSuspended(int $moderatorId, bool $suspended, ?string $suspendedUntil = null): bool
    {
        if ($suspended && $suspendedUntil === null) {
            throw new InvalidArgumentException('A suspension end time is required.');
        }
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                "UPDATE admin_users SET suspended = ?, suspended_until = ?
                 WHERE id = ? AND role = 'moderator' AND suspended <> ?"
            );
            $value = $suspended ? 1 : 0;
            $statement->execute([$value, $suspended ? $suspendedUntil : null, $moderatorId, $value]);
            $changed = $statement->rowCount() > 0;
            if ($changed && $suspended) {
                $this->closeSessions($moderatorId, 'suspended');
            }
            $this->db->commit();
            return $changed;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function remove(int $moderatorId): bool
    {
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare("SELECT id FROM admin_users WHERE id = ? AND role = 'moderator' FOR UPDATE");
            $statement->execute([$moderatorId]);
            if ($statement->fetchColumn() === false) {
                $this->db->rollBack();
                return false;
            }
            $this->closeSessions($moderatorId, 'removed');
            $this->db->prepare("DELETE FROM admin_users WHERE id = ? AND role = 'moderator'")->execute([$moderatorId]);
            $this->db->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function closeSessions(int $moderatorId, string $reason): void
    {
        $this->db->prepare(
            'UPDATE superadmin_moderator_login_logs SET logout_at = CURRENT_TIMESTAMP, end_reason = ?
             WHERE moderator_id = ? AND logout_at IS NULL'
        )->execute([$reason, $moderatorId]);
    }
}
