<?php
declare(strict_types=1);

/**
 * Read-only integration with the mobile application's tables plus admin-owned
 * moderation state. Mobile content is never copied into admin_app_state.
 */
final class MobileModerationModel
{
    /** Sets up mobile-app moderation queries for this dashboard moderator. */
    public function __construct(private PDO $db, private int $adminId)
    {
    }

    /** Checks whether the mobile-app database tables are available. */
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

    /** Counts mobile posts and users by moderation status. */
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

    /** Counts mobile comments by moderation and report status. */
    public function commentCounts(): array
    {
        if (!$this->available()) return ['total' => 0, 'reported' => 0, 'flagged' => 0];
        $row = $this->db->query(
            "SELECT COUNT(*) AS total,
                    (SELECT COUNT(*) FROM (
                        SELECT m.mobile_comment_id AS comment_id
                        FROM admin_mobile_comment_moderation m
                        JOIN mobile_comments c ON c.id = m.mobile_comment_id
                        WHERE m.report_count > 0
                        UNION
                        SELECT comment_id
                        FROM mobile_comment_reports r
                        JOIN mobile_comments c ON c.id = r.comment_id
                        WHERE LOWER(r.status) = 'pending'
                    ) reported_comments) AS reported,
                    COALESCE(SUM(COALESCE(m.ai_flagged, 0) = 1), 0) AS flagged
             FROM mobile_comments c
             LEFT JOIN admin_mobile_comment_moderation m ON m.mobile_comment_id = c.id"
        )->fetch();
        return [
            'total' => (int)($row['total'] ?? 0),
            'reported' => (int)($row['reported'] ?? 0),
            'flagged' => (int)($row['flagged'] ?? 0),
        ];
    }

    /** Lists pending reports submitted for mobile-app comments. */
    public function commentReports(string $query = ''): array
    {
        if (!$this->available()) return [];
        $sql = "SELECT r.id, r.comment_id, r.reason, r.additional_comments, r.created_at,
                       c.post_id, c.content,
                       p.title, reporter.full_name AS reporter_name, reporter.username AS reporter_username,
                       author.full_name AS author_name, author.username AS author_username
                FROM mobile_comment_reports r
                JOIN mobile_comments c ON c.id = r.comment_id
                JOIN mobile_posts p ON p.id = c.post_id
                JOIN mobile_users author ON author.id = c.user_id
                LEFT JOIN mobile_users reporter ON reporter.id = r.reporter_user_id
                WHERE LOWER(r.status) = 'pending'";
        $params = [];
        if ($query !== '') {
            $sql .= ' AND (c.content LIKE ? OR p.title LIKE ? OR author.username LIKE ? OR reporter.username LIKE ? OR r.reason LIKE ? OR r.additional_comments LIKE ?)';
            $like = '%' . $query . '%';
            $params = [$like, $like, $like, $like, $like, $like];
        }
        $statement = $this->db->prepare($sql . ' ORDER BY r.created_at DESC LIMIT 300');
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** Dismisses one pending mobile comment report. */
    public function dismissCommentReport(int $id): bool
    {
        $statement = $this->db->prepare(
            "UPDATE mobile_comment_reports
             SET status = 'Dismissed'
             WHERE id = ? AND LOWER(status) = 'pending'"
        );
        $statement->execute([$id]);
        return $statement->rowCount() > 0;
    }

    /** Lists dismissed reports submitted for mobile-app comments. */
    public function dismissedCommentReports(string $query = ''): array
    {
        if (!$this->available()) return [];
        $sql = "SELECT r.id, r.comment_id, r.reason, r.additional_comments, r.created_at,
                       c.post_id, c.content, p.title,
                       reporter.full_name AS reporter_name, reporter.username AS reporter_username,
                       author.full_name AS author_name, author.username AS author_username
                FROM mobile_comment_reports r
                JOIN mobile_comments c ON c.id = r.comment_id
                JOIN mobile_posts p ON p.id = c.post_id
                JOIN mobile_users author ON author.id = c.user_id
                LEFT JOIN mobile_users reporter ON reporter.id = r.reporter_user_id
                WHERE LOWER(r.status) = 'dismissed'";
        $params = [];
        if ($query !== '') {
            $sql .= ' AND (c.content LIKE ? OR p.title LIKE ? OR author.username LIKE ? OR reporter.username LIKE ? OR r.reason LIKE ? OR r.additional_comments LIKE ?)';
            $like = '%' . $query . '%';
            $params = [$like, $like, $like, $like, $like, $like];
        }
        $statement = $this->db->prepare($sql . ' ORDER BY r.created_at DESC LIMIT 300');
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** Restores one dismissed mobile comment report to the current reports queue. */
    public function restoreCommentReport(int $id): bool
    {
        $statement = $this->db->prepare(
            "UPDATE mobile_comment_reports
             SET status = 'Pending'
             WHERE id = ? AND LOWER(status) = 'dismissed'"
        );
        $statement->execute([$id]);
        return $statement->rowCount() > 0;
    }

    /** Counts mobile reports by day for the dashboard chart. */
    public function reportCountsByDate(int $days = 14): array
    {
        $days = max(1, min(365, $days));
        $results = [];
        $today = new DateTimeImmutable('today');
        $monthly = $days > 90;
        if ($monthly) {
            $firstMonth = $today->setDate((int)$today->format('Y'), 1, 1);
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

    /** Lists mobile posts filtered by status and search text. */
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

    /** Finds recently published posts flagged by AI moderation. */
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

    /** Finds recently added comments flagged by AI moderation. */
    public function recentlyAiFlaggedComments(): array
    {
        $statement = $this->db->query(
            "SELECT c.id, c.post_id, c.content, p.category AS college, u.full_name
             FROM admin_mobile_comment_moderation m
             JOIN mobile_comments c ON c.id = m.mobile_comment_id
             JOIN mobile_posts p ON p.id = c.post_id
             JOIN mobile_users u ON u.id = c.user_id
             WHERE m.ai_flagged = 1
               AND m.created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY)
             ORDER BY m.created_at DESC
             LIMIT 200"
        );
        return $statement->fetchAll();
    }

    /** Loads one mobile post and its related details by ID. */
    public function post(int $id, bool $includeArchived = false): ?array
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
        if (!$post && $includeArchived) {
            $archivedStatement = $this->db->prepare(
                'SELECT a.*, u.full_name AS current_full_name, u.username AS current_username,
                        u.institutional_email
                 FROM admin_removed_mobile_posts a
                 LEFT JOIN mobile_users u ON u.id = a.mobile_user_id
                 WHERE a.mobile_post_id = ?
                 ORDER BY a.id DESC
                 LIMIT 1'
            );
            $archivedStatement->execute([$id]);
            $archived = $archivedStatement->fetch();
            if (!$archived) return null;

            $post = json_decode((string)($archived['post_data'] ?? ''), true);
            if (!is_array($post) || !$post) {
                $post = [
                    'id' => $archived['mobile_post_id'],
                    'user_id' => $archived['mobile_user_id'],
                    'title' => $archived['title'],
                    'content' => $archived['content'],
                    'category' => $archived['category'],
                    'created_at' => $archived['post_created_at'],
                ];
            }
            $post['id'] = (int)$archived['mobile_post_id'];
            $post['title'] = (string)$archived['title'];
            $post['content'] = $archived['content'];
            $post['category'] = $archived['category'];
            $post['created_at'] = $archived['post_created_at'];
            $post['full_name'] = (string)($archived['current_full_name'] ?: $archived['author_name']);
            $post['username'] = (string)($archived['current_username'] ?: $archived['author_username']);
            $post['institutional_email'] = (string)($archived['institutional_email'] ?? '');
            $post['moderation_status'] = 'removed';
            $post['report_count'] = 0;
            $post['report_reason'] = null;
            $post['ai_flagged'] = (int)$archived['ai_flagged'];
            $post['ai_categories'] = $archived['ai_categories'];
            $post['comments'] = [];
            $post['is_archived'] = true;
            $post['archive_id'] = (int)$archived['id'];
            return $post;
        }
        if (!$post) return null;
        $post = $this->reconcileObviousProfanity([$post])[0];
        $comments = $this->db->prepare(
            "SELECT c.*, u.full_name, u.username, COALESCE(m.status, 'approved') AS moderation_status,
                    COALESCE(m.report_count, 0) + COALESCE(r.pending_report_count, 0) AS report_count,
                    r.report_reasons, m.ai_flagged, m.ai_categories
             FROM mobile_comments c JOIN mobile_users u ON u.id = c.user_id
             LEFT JOIN admin_mobile_comment_moderation m ON m.mobile_comment_id = c.id
             LEFT JOIN (
                 SELECT comment_id, COUNT(*) AS pending_report_count,
                        GROUP_CONCAT(DISTINCT NULLIF(reason, '') ORDER BY created_at DESC SEPARATOR ', ') AS report_reasons
                 FROM mobile_comment_reports
                 WHERE LOWER(status) = 'pending'
                 GROUP BY comment_id
             ) r ON r.comment_id = c.id
             WHERE c.post_id = ? ORDER BY c.created_at ASC"
        );
        $comments->execute([$id]);
        $post['comments'] = $comments->fetchAll();
        $post['is_archived'] = false;
        return $post;
    }

    /** Checks the saved dashboard setting for AI moderation. */
    private function aiModerationEnabled(): bool
    {
        try {
            $value = $this->db->query('SELECT enabled FROM admin_ai_moderation_settings WHERE id = 1')->fetchColumn();
            return $value !== false && (bool)$value;
        } catch (PDOException) {
            return false;
        }
    }

    /** Updates AI flags on obvious profanity when AI moderation is enabled. */
    private function reconcileObviousProfanity(array $posts): array
    {
        if (!$this->aiModerationEnabled()) {
            return $posts;
        }
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

    /** Checks text against the built-in obvious-profanity terms. */
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

    /** Lists mobile comments, optionally filtered by search text. */
    public function comments(string $query = ''): array
    {
        if (!$this->available()) return [];
        $sql = "SELECT c.*, p.title, u.full_name, u.username,
                    COALESCE(m.status, 'approved') AS moderation_status,
                    COALESCE(m.report_count, 0) + COALESCE(r.pending_report_count, 0) AS report_count,
                    m.ai_flagged, m.ai_categories,
                    m.mobile_comment_id AS has_moderation
                FROM mobile_comments c JOIN mobile_posts p ON p.id = c.post_id
                JOIN mobile_users u ON u.id = c.user_id
                LEFT JOIN admin_mobile_comment_moderation m ON m.mobile_comment_id = c.id
                LEFT JOIN (
                    SELECT comment_id, COUNT(*) AS pending_report_count
                    FROM mobile_comment_reports
                    WHERE LOWER(status) = 'pending'
                    GROUP BY comment_id
                ) r ON r.comment_id = c.id";
        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE c.content LIKE ? OR u.username LIKE ? OR p.title LIKE ?';
            $like = '%' . $query . '%';
            $params = [$like, $like, $like];
        }
        $sql .= ' ORDER BY c.created_at DESC LIMIT 300';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $this->reconcileObviousCommentProfanity($statement->fetchAll());
    }

    /** Lists comments currently hidden by moderation so they can be reviewed or restored. */
    public function dismissedComments(string $query = ''): array
    {
        if (!$this->available()) return [];
        $sql = "SELECT c.id, c.post_id, c.content, c.created_at, p.title,
                       u.full_name, u.username, m.ai_flagged, m.ai_categories
                FROM mobile_comments c
                JOIN admin_mobile_comment_moderation m ON m.mobile_comment_id = c.id
                JOIN mobile_posts p ON p.id = c.post_id
                JOIN mobile_users u ON u.id = c.user_id
                WHERE m.status = 'hidden'";
        $params = [];
        if ($query !== '') {
            $sql .= ' AND (c.content LIKE ? OR u.username LIKE ? OR p.title LIKE ?)';
            $like = '%' . $query . '%';
            $params = [$like, $like, $like];
        }
        $statement = $this->db->prepare($sql . ' ORDER BY c.created_at DESC LIMIT 300');
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** Updates AI flags on obvious profanity in comments when enabled. */
    private function reconcileObviousCommentProfanity(array $comments): array
    {
        if (!$this->aiModerationEnabled()) {
            return $comments;
        }
        foreach ($comments as &$comment) {
            if (!empty($comment['has_moderation']) || !$this->containsObviousProfanity((string)$comment['content'])) {
                continue;
            }
            $comment['ai_flagged'] = 1;
            $comment['ai_categories'] = 'profanity';
            $comment['moderation_status'] = 'hidden';
            $this->db->prepare(
                "INSERT INTO admin_mobile_comment_moderation
                    (mobile_comment_id, status, ai_flagged, ai_categories)
                 VALUES (?, 'hidden', 1, 'profanity')
                 ON DUPLICATE KEY UPDATE ai_flagged = 1, ai_categories = 'profanity'"
            )->execute([(int)$comment['id']]);
        }
        unset($comment);
        return $comments;
    }

    /** Loads recent posts, comments, and reports for one mobile user. */
    public function userActivity(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT u.*, COALESCE(m.status, "active") AS account_status
             FROM mobile_users u
             LEFT JOIN admin_mobile_user_moderation m ON m.mobile_user_id = u.id
             WHERE u.id = ?'
        );
        $statement->execute([$id]);
        $user = $statement->fetch();
        if (!$user) return null;

        $posts = $this->db->prepare(
            "SELECT p.*, COALESCE(m.status, 'pending') AS moderation_status, m.ai_flagged
             FROM mobile_posts p
             LEFT JOIN admin_mobile_post_moderation m ON m.mobile_post_id = p.id
             WHERE p.user_id = ? ORDER BY p.created_at DESC"
        );
        $posts->execute([$id]);
        $user['posts'] = $posts->fetchAll();

        $comments = $this->db->prepare(
            "SELECT c.*, p.title, COALESCE(m.status, 'approved') AS moderation_status, m.ai_flagged
             FROM mobile_comments c
             JOIN mobile_posts p ON p.id = c.post_id
             LEFT JOIN admin_mobile_comment_moderation m ON m.mobile_comment_id = c.id
             WHERE c.user_id = ? ORDER BY c.created_at DESC"
        );
        $comments->execute([$id]);
        $user['comments'] = $comments->fetchAll();
        return $user;
    }

    /** Lists mobile reports filtered by status and search text. */
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

    /** Changes the moderation status of a mobile report. */
    public function setReportStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['Pending', 'Reviewed', 'Resolved', 'Dismissed'], true)) return false;
        $statement = $this->db->prepare(
            'UPDATE mobile_reports SET status = ? WHERE id = ?'
        );
        $statement->execute([$status, $id]);
        return $statement->rowCount() > 0;
    }

    /** Lists mobile users and their current moderation status. */
    public function users(string $query = ''): array
    {
        if (!$this->available()) return [];
        $sql = 'SELECT u.*, COALESCE(m.status, "active") AS moderation_status,
                m.suspended_until, m.suspension_timezone_offset,
                COUNT(DISTINCT p.id) posts_count, COUNT(DISTINCT c.id) comments_count,
                COALESCE(MAX(violations.violation_count), 0) violation_count
                FROM mobile_users u LEFT JOIN mobile_posts p ON p.user_id = u.id
                LEFT JOIN mobile_comments c ON c.user_id = u.id
                LEFT JOIN admin_mobile_user_moderation m ON m.mobile_user_id = u.id
                LEFT JOIN (
                    SELECT flagged.user_id, COUNT(*) AS violation_count
                    FROM (
                        SELECT flagged_posts.user_id
                        FROM mobile_posts flagged_posts
                        JOIN admin_mobile_post_moderation post_moderation
                            ON post_moderation.mobile_post_id = flagged_posts.id
                        WHERE post_moderation.ai_flagged = 1
                        UNION ALL
                        SELECT flagged_comments.user_id
                        FROM mobile_comments flagged_comments
                        JOIN admin_mobile_comment_moderation comment_moderation
                            ON comment_moderation.mobile_comment_id = flagged_comments.id
                        WHERE comment_moderation.ai_flagged = 1
                        UNION ALL
                        SELECT mobile_user_id AS user_id
                        FROM admin_removed_mobile_posts
                        UNION ALL
                        SELECT mobile_user_id AS user_id
                        FROM admin_removed_mobile_comments
                        WHERE ai_flagged = 1
                    ) flagged
                    GROUP BY flagged.user_id
                ) violations ON violations.user_id = u.id';
        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE u.full_name LIKE ? OR u.username LIKE ? OR u.institutional_email LIKE ?';
            $like = '%' . $query . '%';
            $params = [$like, $like, $like];
        }
        $sql .= ' GROUP BY u.id, m.status, m.suspended_until, m.suspension_timezone_offset ORDER BY u.created_at DESC LIMIT 300';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** Restores expired suspensions using UTC deadlines and the browser zone for legacy records. */
    public function restoreExpiredUserSuspensions(?int $legacyTimezoneOffset = null): int
    {
        if (!$this->available()) return 0;
        $legacyNow = $legacyTimezoneOffset !== null && abs($legacyTimezoneOffset) <= 840
            ? (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->modify(sprintf('%+d minutes', -$legacyTimezoneOffset))
                ->format('Y-m-d H:i:s')
            : (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $restoreLegacy = $this->db->prepare(
            "UPDATE admin_mobile_user_moderation
             SET status = 'active', suspended_until = NULL, reason = NULL, moderated_at = CURRENT_TIMESTAMP
             WHERE status = 'suspended' AND suspension_timezone_offset IS NULL AND suspended_until <= ?"
        );
        $restoreLegacy->execute([$legacyNow]);

        $restoreUtc = $this->db->prepare(
            "UPDATE admin_mobile_user_moderation
             SET status = 'active', suspended_until = NULL, suspension_timezone_offset = NULL,
                 reason = NULL, moderated_at = CURRENT_TIMESTAMP
             WHERE status = 'suspended' AND suspension_timezone_offset IS NOT NULL AND suspended_until <= ?"
        );
        $restoreUtc->execute([(new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s')]);
        return $restoreLegacy->rowCount() + $restoreUtc->rowCount();
    }

    /** Returns IDs and statuses for refreshing the Users page without content details. */
    public function userStatuses(): array
    {
        if (!$this->available()) return [];
        return $this->db->query(
            'SELECT u.id, COALESCE(m.status, "active") AS moderation_status
             FROM mobile_users u
             LEFT JOIN admin_mobile_user_moderation m ON m.mobile_user_id = u.id'
        )->fetchAll();
    }

    /** Updates a mobile post's status and optional moderation reason. */
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

    /** Lists archived mobile posts matching the search text. */
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

    /** Restores a mobile post from the removed-post archive. */
    public function restorePost(int $archiveId): bool
    {
        $statement = $this->db->prepare('SELECT * FROM admin_removed_mobile_posts WHERE id = ?');
        $statement->execute([$archiveId]);
        $archived = $statement->fetch();
        if (!$archived) {
            return false;
        }

        $row = json_decode((string)($archived['post_data'] ?? ''), true);
        if (!is_array($row) || !$row) {
            $row = [
                'id' => $archived['mobile_post_id'],
                'user_id' => $archived['mobile_user_id'],
                'title' => $archived['title'],
                'category' => $archived['category'],
                'content' => $archived['content'],
                'created_at' => $archived['post_created_at'],
            ];
        }
        $columns = $this->db->query('SHOW COLUMNS FROM mobile_posts')->fetchAll(PDO::FETCH_COLUMN);
        $row = array_intersect_key($row, array_flip($columns));
        if (!isset($row['id'], $row['user_id'])) {
            return false;
        }

        $exists = $this->db->prepare('SELECT 1 FROM mobile_posts WHERE id = ?');
        $exists->execute([(int)$row['id']]);
        if ($exists->fetchColumn() !== false) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $names = array_keys($row);
            $insert = $this->db->prepare(
                'INSERT INTO mobile_posts (`' . implode('`, `', $names) . '`) VALUES ('
                . implode(', ', array_fill(0, count($names), '?')) . ')'
            );
            $insert->execute(array_values($row));
            $this->db->prepare('DELETE FROM admin_removed_mobile_posts WHERE id = ?')->execute([$archiveId]);
            $this->db->prepare(
                "INSERT INTO admin_mobile_post_moderation (mobile_post_id, status, moderated_by, moderated_at)
                 VALUES (?, 'approved', ?, CURRENT_TIMESTAMP)
                 ON DUPLICATE KEY UPDATE status = 'approved', moderated_by = VALUES(moderated_by), moderated_at = CURRENT_TIMESTAMP"
            )->execute([(int)$row['id'], $this->adminId]);
            $this->db->prepare(
                "INSERT INTO mobile_notifications (user_id, type, title, message, post_id)
                 VALUES (?, 'moderation', ?, ?, ?)"
            )->execute([
                (int)$row['user_id'],
                'Post restored',
                'Your post "' . (string)$archived['title'] . '" has been restored.',
                (int)$row['id'],
            ]);
            $this->db->commit();
            return true;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    /** Archives and removes a mobile post with a moderation reason. */
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

        $rawStatement = $this->db->prepare('SELECT * FROM mobile_posts WHERE id = ?');
        $rawStatement->execute([$id]);
        $rawPost = $rawStatement->fetch() ?: [];

        $this->db->beginTransaction();
        try {
            $archive = $this->db->prepare(
                'INSERT INTO admin_removed_mobile_posts
                    (mobile_post_id, mobile_user_id, title, content, category, author_name, author_username,
                     ai_flagged, ai_categories, reason, removed_by, removed_by_name, post_created_at, post_data)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
                json_encode($rawPost, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
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

    /** Lists archived mobile comments matching the search text. */
    public function removedComments(string $query = ''): array
    {
        $sql = 'SELECT * FROM admin_removed_mobile_comments';
        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE content LIKE ? OR post_title LIKE ? OR author_username LIKE ? OR author_name LIKE ?';
            $like = '%' . $query . '%';
            $params = [$like, $like, $like, $like];
        }
        $statement = $this->db->prepare($sql . ' ORDER BY removed_at DESC, id DESC LIMIT 200');
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** Restores a mobile comment from the removed-comment archive. */
    public function restoreComment(int $archiveId): bool
    {
        $statement = $this->db->prepare('SELECT * FROM admin_removed_mobile_comments WHERE id = ?');
        $statement->execute([$archiveId]);
        $archived = $statement->fetch();
        if (!$archived) {
            return false;
        }

        $row = json_decode((string)($archived['comment_data'] ?? ''), true);
        if (!is_array($row) || !$row) {
            $row = [
                'id' => $archived['mobile_comment_id'],
                'post_id' => $archived['mobile_post_id'],
                'user_id' => $archived['mobile_user_id'],
                'content' => $archived['content'],
                'created_at' => $archived['comment_created_at'],
            ];
        }
        $columns = $this->db->query('SHOW COLUMNS FROM mobile_comments')->fetchAll(PDO::FETCH_COLUMN);
        $row = array_intersect_key($row, array_flip($columns));
        if (!isset($row['id'], $row['post_id'], $row['user_id'])) {
            return false;
        }

        $parent = $this->db->prepare('SELECT 1 FROM mobile_posts WHERE id = ?');
        $parent->execute([(int)$row['post_id']]);
        $exists = $this->db->prepare('SELECT 1 FROM mobile_comments WHERE id = ?');
        $exists->execute([(int)$row['id']]);
        if ($parent->fetchColumn() === false || $exists->fetchColumn() !== false) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $names = array_keys($row);
            $this->db->prepare(
                'INSERT INTO mobile_comments (`' . implode('`, `', $names) . '`) VALUES ('
                . implode(', ', array_fill(0, count($names), '?')) . ')'
            )->execute(array_values($row));
            $this->db->prepare('DELETE FROM admin_removed_mobile_comments WHERE id = ?')->execute([$archiveId]);
            $this->db->prepare(
                "INSERT INTO mobile_notifications (user_id, type, title, message, post_id)
                 VALUES (?, 'moderation', ?, ?, ?)"
            )->execute([
                (int)$row['user_id'],
                'Comment restored',
                'Your comment on "' . (string)($archived['post_title'] ?? 'a post') . '" has been restored.',
                (int)$row['post_id'],
            ]);
            $this->db->commit();
            return true;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    /** Archives and removes a mobile comment with a moderation reason. */
    public function removeComment(int $id, string $reason = 'Violating community guidelines'): bool
    {
        $statement = $this->db->prepare(
            'SELECT c.id, c.user_id, c.post_id, c.content, c.created_at, p.title AS post_title,
                    u.full_name, u.username, m.ai_flagged, m.ai_categories,
                    (SELECT name FROM admin_users WHERE id = ?) AS admin_name
             FROM mobile_comments c
             LEFT JOIN mobile_posts p ON p.id = c.post_id
             LEFT JOIN mobile_users u ON u.id = c.user_id
             LEFT JOIN admin_mobile_comment_moderation m ON m.mobile_comment_id = c.id
             WHERE c.id = ?'
        );
        $statement->execute([$this->adminId, $id]);
        $comment = $statement->fetch();
        if (!$comment) {
            return false;
        }
        $rawStatement = $this->db->prepare('SELECT * FROM mobile_comments WHERE id = ?');
        $rawStatement->execute([$id]);
        $rawComment = $rawStatement->fetch() ?: [];

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'INSERT INTO admin_removed_mobile_comments
                    (mobile_comment_id, mobile_post_id, mobile_user_id, content, post_title, author_name, author_username,
                     ai_flagged, ai_categories, reason, removed_by, removed_by_name, comment_created_at, comment_data)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $id,
                (int)($comment['post_id'] ?? 0),
                (int)$comment['user_id'],
                $comment['content'],
                $comment['post_title'],
                $comment['full_name'],
                $comment['username'],
                (int)($comment['ai_flagged'] ?? 0),
                $comment['ai_categories'],
                $reason,
                $this->adminId,
                $comment['admin_name'],
                $comment['created_at'],
                json_encode($rawComment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
            ]);
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
            $this->db->prepare(
                "UPDATE mobile_comment_reports
                 SET status = 'Resolved'
                 WHERE comment_id = ? AND LOWER(status) = 'pending'"
            )->execute([$id]);

            $delete = $this->db->prepare('DELETE FROM mobile_comments WHERE id = ?');
            $delete->execute([$id]);

            $this->db->commit();
            return $delete->rowCount() > 0;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    /** Changes the moderation status of a mobile comment. */
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

    /** Records a manual or AI-generated moderation flag on a mobile post. */
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

    /** Suspends or restores a mobile user and records the reason. */
    public function setUserStatus(
        int $id,
        string $status,
        string $reason = '',
        ?string $suspendedUntil = null,
        ?int $timezoneOffset = null
    ): bool
    {
        if (!in_array($status, ['active', 'suspended', 'banned'], true)) return false;
        if ($status === 'suspended' && ($suspendedUntil === null || $timezoneOffset === null)) return false;
        $statement = $this->db->prepare(
            "INSERT INTO admin_mobile_user_moderation
                (mobile_user_id, status, suspended_until, suspension_timezone_offset, reason, moderated_by, moderated_at)
             VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE status=VALUES(status), suspended_until=VALUES(suspended_until),
                suspension_timezone_offset=VALUES(suspension_timezone_offset), reason=VALUES(reason),
                moderated_by=VALUES(moderated_by), moderated_at=CURRENT_TIMESTAMP"
        );
        $statement->execute([
            $id,
            $status,
            $status === 'suspended' ? $suspendedUntil : null,
            $status === 'suspended' ? $timezoneOffset : null,
            trim($reason),
            $this->adminId,
        ]);
        return $statement->rowCount() > 0;
    }
}
