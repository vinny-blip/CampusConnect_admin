<?php
declare(strict_types=1);

final class MobileLoginLogModel
{
    /** Shares the database connection used for mobile session records. */
    public function __construct(private PDO $db)
    {
    }

    /** Counts mobile sessions with activity during the last five minutes. */
    public function activeUserCount(): int
    {
        return (int)$this->db->query(
            "SELECT COUNT(DISTINCT mobile_user_id)
             FROM admin_mobile_login_logs
             WHERE logout_at IS NULL
               AND last_activity_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 5 MINUTE)"
        )->fetchColumn();
    }

    /** Returns recent sessions with explicit or estimated logout status. */
    public function recentSessions(): array
    {
        return $this->db->query(
            "SELECT full_name, username, institutional_email, login_at, last_activity_at, logout_at,
                    CASE
                        WHEN logout_at IS NOT NULL THEN 'Logged out'
                        WHEN last_activity_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 5 MINUTE) THEN 'Active'
                        ELSE 'Timed Out'
                    END AS session_status,
                    CASE
                        WHEN logout_at IS NOT NULL THEN 'logged-out'
                        WHEN last_activity_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 5 MINUTE) THEN 'active'
                        ELSE 'timed-out'
                    END AS status_class,
                    CASE
                        WHEN logout_at IS NULL
                         AND last_activity_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 5 MINUTE)
                        THEN DATE_ADD(last_activity_at, INTERVAL 5 MINUTE)
                        ELSE NULL
                    END AS estimated_logout_at
             FROM admin_mobile_login_logs
             ORDER BY login_at DESC
             LIMIT 500"
        )->fetchAll();
    }
}
