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
            "SELECT u.id, u.name, u.email, u.profile_image, u.suspended, u.suspended_until, u.created_at,
                    (SELECT MAX(COALESCE(l.last_activity_at, l.login_at))
                     FROM superadmin_moderator_login_logs l WHERE l.moderator_id = u.id) AS last_activity_at
             FROM admin_users u WHERE u.role = 'moderator' ORDER BY u.name, u.id"
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
        $sessions = $this->db->query(
            'SELECT id, moderator_name, moderator_email, login_at, last_activity_at, logout_at, end_reason,
                    DATE_ADD(COALESCE(last_activity_at, login_at), INTERVAL 5 MINUTE) AS estimated_logout_at,
                    (logout_at IS NULL AND last_activity_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 5 MINUTE)) AS is_active
             FROM superadmin_moderator_login_logs ORDER BY login_at DESC, id DESC LIMIT 500'
        )->fetchAll();
        foreach ($sessions as &$session) {
            $session['is_active'] = (bool)$session['is_active'];
            $session['state'] = $session['end_reason']
                ?? ($session['is_active'] ? 'signed-in' : 'timed-out');
        }
        unset($session);
        return $sessions;
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
                'INSERT INTO superadmin_moderator_login_logs (moderator_id, moderator_name, moderator_email, last_activity_at)
                 VALUES (?, ?, ?, CURRENT_TIMESTAMP)'
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
        if ($statement->fetchColumn() === false) {
            return false;
        }
        $this->db->prepare(
            'UPDATE superadmin_moderator_login_logs SET last_activity_at = CURRENT_TIMESTAMP
             WHERE id = ? AND moderator_id = ? AND logout_at IS NULL'
        )->execute([$sessionId, $moderatorId]);
        return true;
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
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
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
            if ($ownsTransaction) $this->db->commit();
            return $changed;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function remove(int $moderatorId): bool
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare("SELECT id FROM admin_users WHERE id = ? AND role = 'moderator' FOR UPDATE");
            $statement->execute([$moderatorId]);
            if ($statement->fetchColumn() === false) {
                if ($ownsTransaction) $this->db->rollBack();
                return false;
            }
            $this->closeSessions($moderatorId, 'removed');
            $this->db->prepare("DELETE FROM admin_users WHERE id = ? AND role = 'moderator'")->execute([$moderatorId]);
            if ($ownsTransaction) $this->db->commit();
            return true;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
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
