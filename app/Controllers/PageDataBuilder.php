<?php
declare(strict_types=1);

final class PageDataBuilder
{
    private const MOBILE_LIST_PAGES = [
        'home', 'posts', 'control', 'reported', 'comments', 'removed', 'users', 'mobile-reports',
    ];

    private const REPORT_VIEWS = [
        'current', 'dismissed', 'dismissed-comments', 'removed-posts', 'removed-comments',
    ];

    private ?MobileLoginLogModel $mobileLoginLogs = null;

    public function __construct(
        private string $page,
        private PostModel $posts,
        private NotificationModel $notifications,
        private RestrictedModel $restricted,
        private AiModerationModel $aiModeration,
        private MobileModerationModel $mobile,
        private PDO $db
    ) {
    }

    /** Builds view data from the authenticated user's models and request query. */
    public function build(?array $user, array $query, string $httpHost, string $httpReferer): array
    {
        if (in_array($this->page, ['login', 'register'], true)) {
            return [];
        }

        $data = [
            'user' => $user,
            'noteCount' => $this->notifications->unread(),
        ];

        if (in_array($this->page, self::MOBILE_LIST_PAGES, true)) {
            return array_merge($data, $this->mobileListData($query));
        }

        if ($this->page === 'post') {
            $id = (int)($query['id'] ?? 0);
            $data['post'] = $this->posts->get($id);
            if ($data['post'] !== null) {
                $data['post']['cname'] = (string)$data['post']['community'];
            }
            $data['back'] = isset($query['review'])
                ? 'control.php?status=pending'
                : $this->safeReferrer($httpHost, $httpReferer);
            $data['replyTo'] = trim((string)($query['reply'] ?? ''));
        } elseif ($this->page === 'overview') {
            $data = array_merge($data, $this->overviewData($query));
        } elseif ($this->page === 'notifications') {
            $data['notifications'] = $this->notifications->list();
        } elseif ($this->page === 'logs') {
            $logs = $this->mobileLoginLogs();
            $data['activeUserCount'] = $logs->activeUserCount();
            $data['loginSessions'] = $logs->recentSessions();
        } elseif ($this->page === 'restricted') {
            $data['restrictedUsers'] = $this->restricted->list();
        } elseif (in_array($this->page, ['mobile-feed', 'mobile-queues', 'mobile-comments'], true)) {
            $data = array_merge($data, $this->standaloneMobileData($query));
        } elseif ($this->page === 'user') {
            $available = $this->mobile->available();
            $data['mobileAvailable'] = $available;
            $data['mobileUserActivity'] = $available
                ? $this->mobile->userActivity((int)($query['id'] ?? 0))
                : null;
        } elseif ($this->page === 'mobile-post') {
            $available = $this->mobile->available();
            $data['mobileAvailable'] = $available;
            $data['mobileCounts'] = $this->mobile->counts();
            $data['mobilePost'] = $available
                ? $this->mobile->post((int)($query['id'] ?? 0), ($query['archive'] ?? '') === '1')
                : null;
        } elseif ($this->page === 'search') {
            $data = array_merge($data, $this->searchData($query));
        }

        return $data;
    }

    private function mobileListData(array $query): array
    {
        $data = [
            'mobileAvailable' => $this->mobile->available(),
            'mobileCounts' => $this->mobile->counts(),
            'mobileCommentCounts' => $this->page === 'comments' ? $this->mobile->commentCounts() : [],
            'mobileQuery' => trim((string)($query['q'] ?? '')),
        ];
        $data['mobileStatus'] = $this->page === 'home'
            ? 'approved'
            : ($this->page === 'reported' ? 'pending' : (string)($query['status'] ?? ''));
        $data['mobilePosts'] = in_array($this->page, ['comments', 'reported', 'removed', 'mobile-reports'], true)
            ? []
            : $this->mobile->posts($data['mobileStatus'], $data['mobileQuery']);

        if ($this->page === 'home') {
            $data['mobilePosts'] = array_values(array_filter(
                $data['mobilePosts'],
                static fn(array $post): bool => empty($post['ai_flagged'])
            ));
        }

        $legacyReportStatus = (string)($query['status'] ?? 'pending');
        $legacyReportView = $legacyReportStatus === 'pending' ? 'current' : $legacyReportStatus;
        $requestedReportView = (string)($query['view'] ?? $legacyReportView);
        $data['mobileReportView'] = in_array($requestedReportView, self::REPORT_VIEWS, true)
            ? $requestedReportView
            : 'current';
        $data['mobileRemovedType'] = $this->page === 'removed'
            ? (($query['type'] ?? 'posts') === 'comments' ? 'comments' : 'posts')
            : ($data['mobileReportView'] === 'removed-comments' ? 'comments' : 'posts');

        $reportPage = in_array($this->page, ['reported', 'mobile-reports'], true);
        $showRemovedPosts = $this->page === 'removed'
            ? $data['mobileRemovedType'] === 'posts'
            : $reportPage && $data['mobileReportView'] === 'removed-posts';
        $showRemovedComments = $this->page === 'removed'
            ? $data['mobileRemovedType'] === 'comments'
            : $reportPage && $data['mobileReportView'] === 'removed-comments';
        $data['mobileRemovedPosts'] = $showRemovedPosts
            ? $this->mobile->removedPosts($data['mobileQuery'])
            : [];
        $data['mobileRemovedComments'] = $showRemovedComments
            ? $this->mobile->removedComments($data['mobileQuery'])
            : [];
        $data['mobileDismissedComments'] = $reportPage && $data['mobileReportView'] === 'dismissed-comments'
            ? $this->mobile->dismissedComments($data['mobileQuery'])
            : [];
        if ($this->page === 'users') {
            $data['mobilePosts'] = [];
        }

        $data['mobileComments'] = $this->page === 'comments'
            ? $this->mobile->comments($data['mobileQuery'])
            : [];
        $data['mobileUsers'] = in_array($this->page, ['home', 'users'], true)
            ? $this->mobile->users($this->page === 'users' ? $data['mobileQuery'] : '')
            : [];
        $userFilter = $query['status'] ?? '';
        $data['mobileUserFilter'] = in_array($userFilter, ['active', 'suspended', 'banned'], true)
            ? (string)$userFilter
            : '';
        $statusCounts = array_count_values(array_column($data['mobileUsers'], 'moderation_status'));
        $data['mobileUserCounts'] = [
            'total' => count($data['mobileUsers']),
            'suspended' => $statusCounts['suspended'] ?? 0,
            'banned' => $statusCounts['banned'] ?? 0,
        ];
        if ($this->page === 'users' && $data['mobileUserFilter'] !== '') {
            $data['mobileUsers'] = array_values(array_filter(
                $data['mobileUsers'],
                static fn(array $user): bool => $user['moderation_status'] === $data['mobileUserFilter']
            ));
        }

        $reportStatus = $data['mobileReportView'] === 'current'
            ? 'pending'
            : $data['mobileReportView'];
        $data['mobileReports'] = $reportPage
            && in_array($data['mobileReportView'], ['current', 'reviewed', 'resolved', 'dismissed'], true)
            ? $this->mobile->reports($reportStatus, $data['mobileQuery'])
            : [];
        $data['mobileReportStatus'] = $reportStatus;
        $data['aiModerationEnabled'] = $this->aiModeration->enabled();
        $data['aiModerationConfigured'] = $this->aiModeration->configured();
        $reportDays = $this->reportDays($query);
        $data['reportRange'] = $reportDays;
        $data['reportCountsByDate'] = $this->page === 'home'
            ? $this->mobile->reportCountsByDate($reportDays)
            : [];

        return $data;
    }

    private function overviewData(array $query): array
    {
        $counts = $this->mobile->counts();
        $counts['toApprove'] = $counts['pending'];
        $reportDays = $this->reportDays($query);

        return [
            'counts' => $counts,
            'activity' => [],
            'reportRange' => $reportDays,
            'reportCountsByDate' => $this->mobile->reportCountsByDate($reportDays),
        ];
    }

    private function standaloneMobileData(array $query): array
    {
        $data = [
            'mobileAvailable' => $this->mobile->available(),
            'mobileCounts' => $this->mobile->counts(),
            'mobileQuery' => trim((string)($query['q'] ?? '')),
        ];
        if ($this->page === 'mobile-comments') {
            $data['mobileComments'] = $this->mobile->comments($data['mobileQuery']);
            return $data;
        }

        $status = $this->page === 'mobile-queues'
            ? (string)($query['status'] ?? 'pending')
            : (string)($query['status'] ?? '');
        $data['mobilePosts'] = $this->mobile->posts($status, $data['mobileQuery']);
        $data['mobileStatus'] = $status;
        return $data;
    }

    private function searchData(array $query): array
    {
        $status = (string)($query['status'] ?? '');
        if (!in_array($status, ['pending', 'approved', 'removed'], true)) {
            $status = '';
        }
        $search = trim((string)($query['q'] ?? ''));
        $posts = $this->posts->find(null, null, $search);

        return [
            'feedTitle' => $search !== '' ? 'Results for “' . $search . '”' : 'Search',
            'feedSub' => $search !== '' ? count($posts) . ' posts found.' : 'Type something in the search bar above.',
            'filter' => $status ?: 'all',
            'feedPosts' => $this->withCommunityNames($posts),
            'query' => $search,
            'mode' => $this->page,
            'topLikedToday' => [],
            'reportCountsByDate' => [],
        ];
    }

    private function withCommunityNames(array $posts): array
    {
        foreach ($posts as &$post) {
            $post['cname'] = (string)($post['community'] ?? '');
        }
        unset($post);
        return $posts;
    }

    private function reportDays(array $query): int
    {
        $range = (string)($query['report_range'] ?? '7');
        return in_array($range, ['7', '30', '365'], true) ? (int)$range : 7;
    }

    private function safeReferrer(string $httpHost, string $httpReferer): string
    {
        $parts = parse_url($httpReferer);
        $host = strtolower((string)parse_url('http://' . $httpHost, PHP_URL_HOST));
        $referrerHost = strtolower((string)($parts['host'] ?? $host));
        $filename = isset($parts['path']) ? basename($parts['path']) : '';
        $query = (string)($parts['query'] ?? '');
        if ($host !== '' && $referrerHost === $host && preg_match('/^[a-z]+\.php$/', $filename)
            && preg_match('/^[a-zA-Z0-9_=&%-]*$/', $query)) {
            return $filename . ($query === '' ? '' : '?' . $query);
        }

        return 'posts.php';
    }

    private function mobileLoginLogs(): MobileLoginLogModel
    {
        if ($this->mobileLoginLogs === null) {
            $this->mobileLoginLogs = new MobileLoginLogModel($this->db);
        }
        return $this->mobileLoginLogs;
    }
}
