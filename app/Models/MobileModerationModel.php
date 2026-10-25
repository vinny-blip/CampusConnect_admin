<?php
declare(strict_types=1);

/**
 * Read-only integration with the mobile application's tables plus admin-owned
 * moderation state. Mobile content is never copied into admin_app_state.
 */
final class MobileModerationModel
{
    public function __construct(private PDO $db, private int $adminId)
    {
    }

    public function available(): bool
    {
        try {
            $this->db->query('SELECT id FROM mobile_users LIMIT 0');
            $this->db->query('SELECT id FROM mobile_posts LIMIT 0');
            $this->db->query('SELECT id FROM mobile_comments LIMIT 0');
            $this->db->query('SELECT id FROM mobile_reports LIMIT 0');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    public function counts(): array
    {
        if (!$this->available()) {
            return ['posts' => 0, 'comments' => 0, 'users' => 0, 'reported' => 0, 'pending' => 0, 'flagged' => 0, 'approved' => 0, 'removed' => 0];
        }
        return [
            'posts' => (int)$this->db->query('SELECT COUNT(*) FROM mobile_posts')->fetchColumn(),
            'comments' => (int)$this->db->query('SELECT COUNT(*) FROM mobile_comments')->fetchColumn(),
            'users' => (int)$this->db->query('SELECT COUNT(*) FROM mobile_users')->fetchColumn(),
            'reported' => (int)$this->db->query(
                "SELECT COUNT(*) FROM mobile_reports WHERE LOWER(status) = 'pending'"
            )->fetchColumn(),
            'pending' => (int)$this->db->query(
                "SELECT COUNT(*) FROM mobile_posts p LEFT JOIN admin_mobile_post_moderation m ON m.mobile_post_id = p.id
                 WHERE COALESCE(m.status, 'pending') = 'pending'"
            )->fetchColumn(),
            'flagged' => (int)$this->db->query(
                "SELECT COUNT(*) FROM mobile_posts p
                 JOIN admin_mobile_post_moderation m ON m.mobile_post_id = p.id
                 WHERE m.ai_flagged = 1"
            )->fetchColumn(),
            'approved' => (int)$this->db->query(
                "SELECT COUNT(*) FROM mobile_posts p LEFT JOIN admin_mobile_post_moderation m ON m.mobile_post_id = p.id
                 WHERE COALESCE(m.status, 'pending') = 'approved'"
            )->fetchColumn(),
            'removed' => (int)$this->db->query(
                "SELECT COUNT(*) FROM mobile_posts p JOIN admin_mobile_post_moderation m ON m.mobile_post_id = p.id
                 WHERE m.status = 'removed'"
            )->fetchColumn(),
        ];
    }

    public function reportCountsByDate(int $days = 14): array
    {
        $days = max(1, min(365, $days));
        $results = [];
        $today = new DateTimeImmutable('today');
        $monthly = $days > 90;
        if ($monthly) {
            $firstMonth = $today->modify('first day of this month')->modify('-11 months');
            $monthCount = 12;
            if (!$this->available()) {
                for ($offset = 0; $offset < $monthCount; $offset++) {
                    $month = $firstMonth->modify("+{$offset} months");
                    $results[] = ['date' => $month->format('Y-m-01'), 'label' => $month->format('M'), 'count' => 0];
                }
                return $results;
            }

            $statement = $this->db->prepare(
                "SELECT DATE_FORMAT(created_at, '%Y-%m-01') AS report_month, COUNT(*) AS report_count
                 FROM mobile_reports
                 WHERE created_at >= ?
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m-01')"
            );
            $statement->execute([$firstMonth->format('Y-m-d 00:00:00')]);
            $counts = [];
            foreach ($statement->fetchAll() as $row) {
                $counts[$row['report_month']] = (int)$row['report_count'];
            }
            for ($offset = 0; $offset < $monthCount; $offset++) {
                $month = $firstMonth->modify("+{$offset} months");
                $key = $month->format('Y-m-01');
                $results[] = ['date' => $key, 'label' => $month->format('M'), 'count' => $counts[$key] ?? 0];
            }
            return $results;
        }

        if (!$this->available()) {
            for ($offset = $days - 1; $offset >= 0; $offset--) {
                $date = $today->modify("-{$offset} days");
                $results[] = ['date' => $date->format('Y-m-d'), 'label' => $date->format('M j'), 'count' => 0];
            }
            return $results;
        }

        $statement = $this->db->prepare(
            'SELECT DATE(created_at) AS report_date, COUNT(*) AS report_count
             FROM mobile_reports
             WHERE created_at >= ?
             GROUP BY DATE(created_at)'
        );
        $statement->execute([$today->modify("-" . ($days - 1) . " days")->format('Y-m-d 00:00:00')]);
        $counts = [];
        foreach ($statement->fetchAll() as $row) {
            $counts[$row['report_date']] = (int)$row['report_count'];
        }
        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = $today->modify("-{$offset} days");
            $key = $date->format('Y-m-d');
            $results[] = ['date' => $key, 'label' => $date->format('M j'), 'count' => $counts[$key] ?? 0];
        }
        return $results;
    }

    public function posts(string $status = '', string $query = ''): array
    {
        if (!$this->available()) {
            return [];
        }
        $where = [];
        $params = [];
        if (in_array($status, ['pending', 'approved', 'removed'], true)) {
            $where[] = "COALESCE(m.status, 'pending') = ?";
            $params[] = $status;
        }
        if ($query !== '') {
            $where[] = '(p.title LIKE ? OR p.content LIKE ? OR p.category LIKE ? OR u.username LIKE ?)';
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $sql = "SELECT p.*, u.full_name, u.username,
                    COALESCE(m.status, 'pending') AS moderation_status,
                    COALESCE(r.report_count, 0) AS report_count, r.report_reason,
                    m.ai_flagged, m.ai_categories
                FROM mobile_posts p JOIN mobile_users u ON u.id = p.user_id
                LEFT JOIN admin_mobile_post_moderation m ON m.mobile_post_id = p.id
                LEFT JOIN (
                    SELECT post_id, COUNT(*) AS report_count,
                           SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(reason, '') ORDER BY created_at DESC), ',', 1) AS report_reason
                    FROM mobile_reports
                    WHERE LOWER(status) = 'pending'
                    GROUP BY post_id
                ) r ON r.post_id = p.id";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY p.created_at DESC LIMIT 200';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $this->reconcileObviousProfanity($statement->fetchAll());
    }

    public function recentlyAiFlaggedPosts(): array
    {
        $statement = $this->db->query(
            "SELECT p.id, p.title, p.category AS college, u.full_name, m.ai_categories
             FROM admin_mobile_post_moderation m
             JOIN mobile_posts p ON p.id = m.mobile_post_id
             JOIN mobile_users u ON u.id = p.user_id
             WHERE m.ai_flagged = 1
               AND m.created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY)
             ORDER BY m.created_at DESC
             LIMIT 200"
        );
        return $statement->fetchAll();
    }

    public function post(int $id): ?array
    {
        $statement = $this->db->prepare(
            "SELECT p.*, u.full_name, u.username, u.institutional_email,
                    COALESCE(m.status, 'pending') AS moderation_status,
                    COALESCE(r.report_count, 0) AS report_count, r.report_reason,
                    m.ai_flagged, m.ai_categories
             FROM mobile_posts p JOIN mobile_users u ON u.id = p.user_id
             LEFT JOIN admin_mobile_post_moderation m ON m.mobile_post_id = p.id
             LEFT JOIN (
                 SELECT post_id, COUNT(*) AS report_count,
                        SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(reason, '') ORDER BY created_at DESC), ',', 1) AS report_reason
                 FROM mobile_reports
                 WHERE LOWER(status) = 'pending'
                 GROUP BY post_id
             ) r ON r.post_id = p.id
             WHERE p.id = ?"
        );
        $statement->execute([$id]);
        $post = $statement->fetch();
        if (!$post) return null;
        $post = $this->reconcileObviousProfanity([$post])[0];
        $comments = $this->db->prepare(
            "SELECT c.*, u.full_name, u.username, COALESCE(m.status, 'approved') AS moderation_status,
                    COALESCE(m.report_count, 0) AS report_count, m.ai_flagged, m.ai_categories
             FROM mobile_comments c JOIN mobile_users u ON u.id = c.user_id
             LEFT JOIN admin_mobile_comment_moderation m ON m.mobile_comment_id = c.id
             WHERE c.post_id = ? ORDER BY c.created_at ASC"
        );
        $comments->execute([$id]);
        $post['comments'] = $comments->fetchAll();
        return $post;
    }

    private function reconcileObviousProfanity(array $posts): array
    {
        foreach ($posts as &$post) {
            if (!$this->containsObviousProfanity((string)$post['title'] . "\n" . (string)$post['content'])) {
                continue;
            }

            $post['ai_flagged'] = 1;
            $post['ai_categories'] = 'profanity';
            $post['moderation_status'] = 'pending';

            $statement = $this->db->prepare(
                "INSERT INTO admin_mobile_post_moderation
                    (mobile_post_id, status, ai_flagged, ai_categories)
                 VALUES (?, 'pending', 1, 'profanity')
                 ON DUPLICATE KEY UPDATE
                    status = 'pending', ai_flagged = 1, ai_categories = 'profanity'"
            );
            $statement->execute([(int)$post['id']]);
        }
        unset($post);
        return $posts;
    }

    private function containsObviousProfanity(string $text): bool
    {
        $normalized = strtolower(strtr($text, [
            '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a',
            '5' => 's', '7' => 't', '@' => 'a', '$' => 's',
        ]));
        if (preg_match('/\b(fuck|fucking|fucker|motherfucker|shit|bullshit|bitch|bastard|dick|cunt|slut|whore)\b/i', $normalized) === 1) {
            return true;
        }
        $compact = preg_replace('/[^a-z]/', '', $normalized);
        if (!is_string($compact)) return false;
        foreach (['fuck', 'shit', 'bitch', 'bastard', 'dick', 'cunt', 'slut', 'whore'] as $term) {
            if (strpos($compact, $term) !== false) return true;
        }
        return false;
    }

    public function comments(string $query = ''): array
    {
        if (!$this->available()) return [];
        $sql = "SELECT c.*, p.title, u.full_name, u.username,
                    COALESCE(m.status, 'approved') AS moderation_status,
                    COALESCE(m.report_count, 0) AS report_count, m.ai_flagged, m.ai_categories
                FROM mobile_comments c JOIN mobile_posts p ON p.id = c.post_id
                JOIN mobile_users u ON u.id = c.user_id
                LEFT JOIN admin_mobile_comment_moderation m ON m.mobile_comment_id = c.id";
        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE c.content LIKE ? OR u.username LIKE ? OR p.title LIKE ?';
            $like = '%' . $query . '%';
            $params = [$like, $like, $like];
        }
        $sql .= ' ORDER BY c.created_at DESC LIMIT 300';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function reports(string $status = 'pending', string $query = ''): array
    {
        if (!$this->available()) return [];
        $where = [];
        $params = [];
        if (in_array($status, ['pending', 'reviewed', 'resolved', 'dismissed'], true)) {
            $where[] = 'LOWER(r.status) = ?';
            $params[] = $status;
        }
        if ($query !== '') {
            $where[] = '(p.title LIKE ? OR p.content LIKE ? OR r.reason LIKE ? OR r.additional_comments LIKE ? OR reporter.username LIKE ?)';
            $like = '%' . $query . '%';
            $params = array_merge($params, [$like, $like, $like, $like, $like]);
        }
        $sql = "SELECT r.id, r.post_id, r.reason, r.additional_comments, r.status, r.created_at,
                       p.title, p.content, p.category,
                       reporter.full_name AS reporter_name, reporter.username AS reporter_username,
                       author.full_name AS author_name, author.username AS author_username
                FROM mobile_reports r
                JOIN mobile_posts p ON p.id = r.post_id
                JOIN mobile_users reporter ON reporter.id = r.reporter_user_id
                JOIN mobile_users author ON author.id = p.user_id";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY r.created_at DESC LIMIT 300';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function setReportStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['Pending', 'Reviewed', 'Resolved', 'Dismissed'], true)) return false;
        $statement = $this->db->prepare(
            'UPDATE mobile_reports SET status = ? WHERE id = ?'
        );
        $statement->execute([$status, $id]);
        return $statement->rowCount() > 0;
    }

    public function users(string $query = ''): array
    {
        if (!$this->available()) return [];
        $sql = 'SELECT u.*, COALESCE(m.status, "active") AS moderation_status,
                COUNT(DISTINCT p.id) posts_count, COUNT(DISTINCT c.id) comments_count
                FROM mobile_users u LEFT JOIN mobile_posts p ON p.user_id = u.id
                LEFT JOIN mobile_comments c ON c.user_id = u.id
                LEFT JOIN admin_mobile_user_moderation m ON m.mobile_user_id = u.id';
        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE u.full_name LIKE ? OR u.username LIKE ? OR u.institutional_email LIKE ?';
            $like = '%' . $query . '%';
            $params = [$like, $like, $like];
        }
        $sql .= ' GROUP BY u.id, m.status ORDER BY u.created_at DESC LIMIT 300';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function setPostStatus(int $id, string $status, string $reason = ''): bool
    {
        if (!in_array($status, ['pending', 'approved', 'removed'], true)) return false;
        $statement = $this->db->prepare(
            "INSERT INTO admin_mobile_post_moderation
                (mobile_post_id, status, report_reason, moderated_by, moderated_at)
             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE status=VALUES(status), report_reason=VALUES(report_reason),
                moderated_by=VALUES(moderated_by), moderated_at=CURRENT_TIMESTAMP"
        );
        $statement->execute([$id, $status, trim($reason), $this->adminId]);
        return $statement->rowCount() > 0;
    }

    public function removedPosts(string $query = ''): array
    {
        $sql = 'SELECT * FROM admin_removed_mobile_posts';
        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE title LIKE ? OR content LIKE ? OR category LIKE ? OR author_username LIKE ? OR author_name LIKE ?';
            $like = '%' . $query . '%';
            $params = [$like, $like, $like, $like, $like];
        }
        $statement = $this->db->prepare($sql . ' ORDER BY removed_at DESC, id DESC LIMIT 200');
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function removePost(int $id, string $reason = 'Violating community guidelines'): bool
    {
        $statement = $this->db->prepare(
            'SELECT p.user_id, p.title, p.content, p.category, p.created_at,
                    u.full_name, u.username, m.ai_flagged, m.ai_categories,
                    (SELECT name FROM admin_users WHERE id = ?) AS admin_name
             FROM mobile_posts p
             LEFT JOIN mobile_users u ON u.id = p.user_id
             LEFT JOIN admin_mobile_post_moderation m ON m.mobile_post_id = p.id
             WHERE p.id = ?'
        );
        $statement->execute([$this->adminId, $id]);
        $post = $statement->fetch();
        if (!$post) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $archive = $this->db->prepare(
                'INSERT INTO admin_removed_mobile_posts
                    (mobile_post_id, mobile_user_id, title, content, category, author_name, author_username,
                     ai_flagged, ai_categories, reason, removed_by, removed_by_name, post_created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $archive->execute([
                $id,
                (int)$post['user_id'],
                (string)$post['title'],
                $post['content'],
                $post['category'],
                $post['full_name'],
                $post['username'],
                (int)($post['ai_flagged'] ?? 0),
                $post['ai_categories'],
                $reason,
                $this->adminId,
                $post['admin_name'],
                $post['created_at'],
            ]);
            $notification = $this->db->prepare(
                "INSERT INTO mobile_notifications (user_id, type, title, message, post_id)
                 VALUES (?, 'moderation', ?, ?, ?)"
            );
            $notification->execute([
                (int)$post['user_id'],
                'Post removed',
                'Your post "' . (string)$post['title'] . '" was removed for violating community guidelines.',
                $id,
            ]);
            $delete = $this->db->prepare('DELETE FROM mobile_posts WHERE id = ? AND user_id = ?');
            $delete->execute([$id, (int)$post['user_id']]);
            $this->db->commit();
            return $delete->rowCount() > 0;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function removeComment(int $id, string $reason = 'Violating community guidelines'): bool
    {
        $statement = $this->db->prepare(
            'SELECT c.id, c.user_id, c.post_id, c.content, p.title AS post_title
             FROM mobile_comments c
             LEFT JOIN mobile_posts p ON p.id = c.post_id
             WHERE c.id = ?'
        );
        $statement->execute([$id]);
        $comment = $statement->fetch();
        if (!$comment) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $snippet = trim((string)$comment['content']);
            if (mb_strlen($snippet) > 60) {
                $snippet = mb_substr($snippet, 0, 57) . '...';
            }
            $postContext = !empty($comment['post_title']) ? ' on "' . (string)$comment['post_title'] . '"' : '';
            $message = 'Your comment' . $postContext . ' ("' . $snippet . '") was removed for violating community guidelines.';

            $notification = $this->db->prepare(
                "INSERT INTO mobile_notifications (user_id, type, title, message, post_id)
                 VALUES (?, 'moderation', ?, ?, ?)"
            );
            $notification->execute([
                (int)$comment['user_id'],
                'Comment removed',
                $message,
                (int)($comment['post_id'] ?? 0),
            ]);

            $this->db->prepare('DELETE FROM admin_mobile_comment_moderation WHERE mobile_comment_id = ?')->execute([$id]);

            $delete = $this->db->prepare('DELETE FROM mobile_comments WHERE id = ?');
            $delete->execute([$id]);

            $this->db->commit();
            return $delete->rowCount() > 0;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function setCommentStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['approved', 'hidden', 'removed'], true)) return false;
        $statement = $this->db->prepare(
            "INSERT INTO admin_mobile_comment_moderation
                (mobile_comment_id, status, moderated_by, moderated_at)
             VALUES (?, ?, ?, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE status=VALUES(status), moderated_by=VALUES(moderated_by),
                moderated_at=CURRENT_TIMESTAMP"
        );
        $statement->execute([$id, $status, $this->adminId]);
        return $statement->rowCount() > 0;
    }

    public function flagPost(int $id, string $reason, bool $ai = false): void
    {
        $statement = $this->db->prepare(
            "INSERT INTO admin_mobile_post_moderation
                (mobile_post_id, report_count, report_reason, ai_flagged, ai_categories)
             VALUES (?, 1, ?, ?, ?)
             ON DUPLICATE KEY UPDATE report_count=report_count+1, report_reason=VALUES(report_reason),
                ai_flagged=GREATEST(ai_flagged, VALUES(ai_flagged)), ai_categories=VALUES(ai_categories)"
        );
        $statement->execute([$id, trim($reason), $ai ? 1 : 0, $ai ? 'ai' : null]);
    }

    public function setUserStatus(int $id, string $status, string $reason = ''): bool
    {
        if (!in_array($status, ['active', 'suspended'], true)) return false;
        $statement = $this->db->prepare(
            "INSERT INTO admin_mobile_user_moderation
                (mobile_user_id, status, reason, moderated_by, moderated_at)
             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE status=VALUES(status), reason=VALUES(reason),
                moderated_by=VALUES(moderated_by), moderated_at=CURRENT_TIMESTAMP"
        );
        $statement->execute([$id, $status, trim($reason), $this->adminId]);
        return $statement->rowCount() > 0;
    }
}
