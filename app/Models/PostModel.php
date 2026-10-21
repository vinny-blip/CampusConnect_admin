<?php
declare(strict_types=1);

final class PostModel extends StateModel
{
    private const POSTS = 'posts';
    private const SAVED = 'saved';
    private const LOG = 'log';
    private const REPORT_HISTORY = 'report_history';
    private const REPORT_SAMPLE_VERSION = 'report_sample_version';
    private const SLUGS = ['itters', 'engineering', 'educers', 'cbaers', 'designatmobbin'];
    private const TITLES = [
        'Hi! Can anyone help me with this one, please?',
        'How do I solve this limit problem?',
        'Anyone have notes for Calculus 2 midterms?',
        'Is the library open on weekends?',
        'Looking for a study group for Physics 101',
        'Can someone explain this derivative rule?',
        'Selling my used Engineering textbook',
        'Who has the schedule for enrollment week?',
    ];
    private const REPORTED_EXAMPLES = [
        0 => [
            'title' => 'Paid exam answers and streaming accounts',
            'body' => 'DM me for paid access to exam answer keys and streaming accounts. This is an unrelated promotion, not an academic discussion.',
            'reason' => 'Non-academic content',
        ],
        6 => [
            'title' => 'Personal attack in the calculus discussion',
            'body' => 'You are clueless and should stop asking basic questions. Nobody wants to help you.',
            'reason' => 'Toxic or harassing content',
        ],
        12 => [
            'title' => 'Uncredited lab conclusion submitted as original work',
            'body' => 'This conclusion was copied from another student’s lab report and is being shared here as original work: “The results support the hypothesis because the measured values followed the predicted pattern.”',
            'reason' => 'Plagiarized content',
        ],
        30 => [
            'title' => 'Repeated tutoring-service advertisement',
            'body' => 'Buy my assignment-writing and tutoring package. Message me for prices. This promotion is unrelated to the course discussion.',
            'reason' => 'Non-academic content',
        ],
    ];
    private const REPORT_REASONS = [
        'Non-academic content', 'Toxic or harassing content', 'Plagiarized content', 'Other guideline violation',
    ];
    private const COMMENTS = [
        ['Juan Dela Cruz', 'The road near Lacson Street is flooded, take another route.'],
        ['Maria Santos', 'Thanks for the update! Very helpful.'],
        ['HANDA Official', 'The evacuation center is open until 8 PM.'],
        ['Ana Reyes', 'Same question here, following this thread.'],
        ['Carlo Lim', 'Try the notes posted in the group files.'],
    ];

    private function all(): array
    {
        $posts = $this->load(self::POSTS, fn() => $this->seedPosts());
        $sampleVersion = $this->load(self::REPORT_SAMPLE_VERSION, static fn() => []);
        if ((int)($sampleVersion[0] ?? 0) < 1) {
            $this->updateSeedReportedExamples($posts);
            $this->save(self::REPORT_SAMPLE_VERSION, [1]);
        }
        return $posts;
    }

    private function seedPosts(): array
    {
        $posts = [];
        foreach (range(0, 39) as $i) {
            $status = $i % 5 === 4 ? 'approved' : 'pending';
            $example = self::REPORTED_EXAMPLES[$i] ?? null;
            $reported = $status === 'pending' && $example !== null;
            $created = $this->ago(5 + $i * 13);
            $commentCount = $i === 0 ? 3 : $i % 5;
            $comments = [];
            for ($k = 0; $k < $commentCount; $k++) {
                $source = self::COMMENTS[($i + $k) % count(self::COMMENTS)];
                $comments[] = [
                    'id' => $k + 1,
                    'author' => $source[0],
                    'text' => $source[1],
                    'created' => $this->ago(15 + $k * 20),
                    'reportedAt' => null,
                    'hidden' => false,
                    'likes' => ($k * 5 + $i) % 15,
                    'hearts' => ($k * 3 + $i) % 12,
                ];
            }
            $posts[] = [
                'id' => $i + 1,
                'author' => 'anonymous' . ($i + 1),
                'community' => self::SLUGS[$i % count(self::SLUGS)],
                'title' => $example['title'] ?? ($i === 0 ? 'Cherish the moments with your friends!' : self::TITLES[$i % count(self::TITLES)]),
                'body' => $example['body'] ?? ($i === 0
                    ? 'Take a moment today to thank the people who made this semester easier. Share a memory with your friends in the comments.'
                    : 'Posted by a student. Replies and notes from classmates are welcome in the comments.'),
                'image' => $this->art($i),
                'created' => $created,
                'removedAt' => null,
                'likes' => ($i * 7) % 45 + 3,
                'liked' => false,
                'pinned' => false,
                'locked' => false,
                'tags' => [],
                'status' => $status,
                'reported' => $reported,
                'reportReason' => $reported ? $example['reason'] : '',
                'reportedAt' => $reported ? $created : null,
                'reviewed' => false,
                'commentList' => $comments,
            ];
            if ($reported) {
                $this->recordReport($created);
            }
        }
        $this->save(self::SAVED, []);
        $this->save(self::LOG, []);
        return $posts;
    }

    private function updateSeedReportedExamples(array &$posts): void
    {
        $changed = false;
        foreach ($posts as &$post) {
            $id = (int)($post['id'] ?? 0);
            $index = $id - 1;
            if ($index < 0 || $index >= 40
                || ($post['author'] ?? '') !== 'anonymous' . $id
                || ($post['status'] ?? '') !== 'pending') {
                continue;
            }
            $defaultTitle = $index === 0
                ? 'Cherish the moments with your friends!'
                : self::TITLES[$index % count(self::TITLES)];
            $defaultBody = $index === 0
                ? 'Take a moment today to thank the people who made this semester easier. Share a memory with your friends in the comments.'
                : 'Posted by a student. Replies and notes from classmates are welcome in the comments.';
            if (($post['title'] ?? '') !== $defaultTitle || ($post['body'] ?? '') !== $defaultBody || empty($post['reported'])) {
                continue;
            }

            $example = self::REPORTED_EXAMPLES[$index] ?? null;
            if ($example !== null) {
                $post['title'] = $example['title'];
                $post['body'] = $example['body'];
                $post['reportReason'] = $example['reason'];
                if (empty($post['reportedAt'])) {
                    $post['reportedAt'] = (int)$post['created'];
                    $this->recordReport((int)$post['reportedAt']);
                }
            } else {
                $post['reported'] = false;
                $post['reportReason'] = '';
                $post['reportedAt'] = null;
            }
            $changed = true;
        }
        unset($post);
        if ($changed) {
            $this->save(self::POSTS, $posts);
        }
    }

    private function ago(int $minutes): int
    {
        return (int)(microtime(true) * 1000) - $minutes * 60000;
    }

    private function art(int $seed): string
    {
        $palette = [['#9ccbff', '#b2b0d6'], ['#c8e6a0', '#8fcf7a'], ['#ffd9a0', '#f4a6a0'], ['#d4c4f0', '#9ccbff'], ['#bfe9e1', '#8bc4d8']];
        $dots = ['#e63946', '#f4a261', '#2a9d8f', '#9b5de5', '#ffd166', '#4d96ff'];
        [$background, $ground] = $palette[$seed % count($palette)];
        $circles = '';
        foreach ($dots as $i => $color) {
            $angle = ($i / 6) * M_PI * 2;
            $x = number_format(160 + cos($angle) * 55, 1, '.', '');
            $y = number_format(95 + sin($angle) * 30, 1, '.', '');
            $circles .= '<circle cx="' . $x . '" cy="' . $y . '" r="14" fill="' . $color . '"/>';
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 190"><rect width="320" height="190" fill="'
            . $background . '"/><ellipse cx="160" cy="105" rx="120" ry="60" fill="' . $ground . '"/>'
            . $circles . '</svg>';
        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
    }

    private function savedIds(): array
    {
        return $this->load(self::SAVED, static fn() => []);
    }

    private function log(string $moderator, string $action, array $post): void
    {
        $entries = $this->load(self::LOG, static fn() => []);
        array_unshift($entries, [
            't' => (int)(microtime(true) * 1000),
            'mod' => $moderator,
            'action' => $action,
            'postId' => $post['id'],
            'title' => $post['title'],
        ]);
        $this->save(self::LOG, array_slice($entries, 0, 30));
    }

    private function update(int $id, callable $change): bool
    {
        $posts = $this->all();
        foreach ($posts as &$post) {
            if ((int)$post['id'] === $id) {
                $change($post);
                unset($post);
                $this->save(self::POSTS, $posts);
                return true;
            }
        }
        unset($post);
        return false;
    }

    private function enrich(array $post): array
    {
        $post['reported'] = (bool)($post['reported'] ?? false);
        $post['reportReason'] = (string)($post['reportReason'] ?? '');
        $post['reportedAt'] = isset($post['reportedAt']) ? (int)$post['reportedAt'] : null;
        $post['aiFlagged'] = (bool)($post['aiFlagged'] ?? false);
        $post['aiNeedsReview'] = (bool)($post['aiNeedsReview'] ?? false);
        $post['aiCategories'] = is_array($post['aiCategories'] ?? null) ? $post['aiCategories'] : [];
        $post['aiReviewError'] = (bool)($post['aiReviewError'] ?? false);
        $post['removedAt'] = isset($post['removedAt']) ? (int)$post['removedAt'] : null;
        if ($post['status'] === 'removed' && $post['removedAt'] === null) {
            foreach ($this->load(self::LOG, static fn() => []) as $entry) {
                if ((int)($entry['postId'] ?? 0) === (int)$post['id'] && ($entry['action'] ?? '') === 'Removed') {
                    $post['removedAt'] = (int)$entry['t'];
                    break;
                }
            }
        }
        $post['locked'] = (bool)($post['locked'] ?? false);
        $post['tags'] = is_array($post['tags'] ?? null) ? $post['tags'] : [];
        foreach ($post['commentList'] as &$comment) {
            $comment['reported'] = (bool)($comment['reported'] ?? false);
            $comment['reportReason'] = (string)($comment['reportReason'] ?? '');
            $comment['reportedAt'] = isset($comment['reportedAt']) ? (int)$comment['reportedAt'] : null;
            $comment['hidden'] = (bool)($comment['hidden'] ?? false);
            $comment['aiFlagged'] = (bool)($comment['aiFlagged'] ?? false);
            $comment['aiNeedsReview'] = (bool)($comment['aiNeedsReview'] ?? false);
            $comment['aiCategories'] = is_array($comment['aiCategories'] ?? null) ? $comment['aiCategories'] : [];
            $comment['aiReviewError'] = (bool)($comment['aiReviewError'] ?? false);
        }
        unset($comment);
        $post['commentCount'] = count($post['commentList']);
        $post['saved'] = in_array($post['id'], $this->savedIds(), true);
        return $post;
    }

    public function get(int $id): ?array
    {
        foreach ($this->all() as $post) {
            if ((int)$post['id'] === $id) {
                return $this->enrich($post);
            }
        }
        return null;
    }

    public function find(?string $status = null, ?string $community = null, string $query = '', ?bool $reported = null, string $sort = 'new'): array
    {
        $query = mb_strtolower(trim($query));
        $posts = array_filter($this->all(), static function ($post) use ($status, $community, $query, $reported): bool {
            return (!$status || $post['status'] === $status)
                && (!$community || $post['community'] === $community)
                && ($reported === null || (bool)$post['reported'] === $reported)
                && ($query === '' || str_contains(mb_strtolower(
                    $post['title'] . ' ' . $post['body'] . ' ' . implode(' ', $post['tags'] ?? []) . ' '
                    . $post['author'] . ' ' . $post['community']
                ), $query));
        });
        usort($posts, static function ($a, $b) use ($sort): int {
            $pin = (int)$b['pinned'] <=> (int)$a['pinned'];
            if ($pin !== 0) {
                return $pin;
            }
            return $sort === 'hot'
                ? ($b['likes'] <=> $a['likes'])
                : ($b['created'] <=> $a['created']);
        });
        return array_map(fn($post) => $this->enrich($post), array_values($posts));
    }

    public function queue(string $kind, string $query = ''): array
    {
        if ($kind === 'reported') {
            return $this->find(null, null, $query, true);
        }
        if ($kind !== 'ai') {
            return array_values(array_filter(
                $this->find('pending', null, $query, false),
                static fn($post) => empty($post['aiNeedsReview'])
            ));
        }

        $query = mb_strtolower(trim($query));
        $posts = array_filter($this->all(), static fn($post) =>
            !empty($post['aiNeedsReview'])
            && ($query === '' || str_contains(mb_strtolower(
                $post['title'] . ' ' . $post['body'] . ' ' . $post['author'] . ' ' . $post['community']
            ), $query))
        );
        usort($posts, static fn($a, $b) => $b['created'] <=> $a['created']);
        return array_map(fn($post) => $this->enrich($post), array_values($posts));
    }

    public function reportedComments(string $query = ''): array
    {
        return $this->commentQueueItems($query, false);
    }

    public function aiReviewComments(string $query = ''): array
    {
        return $this->commentQueueItems($query, true);
    }

    private function commentQueueItems(string $query, bool $aiReviewOnly): array
    {
        $query = mb_strtolower(trim($query));
        $comments = [];
        foreach ($this->all() as $post) {
            foreach ($post['commentList'] as $comment) {
                $needsReview = $aiReviewOnly
                    ? !empty($comment['aiNeedsReview'])
                    : !empty($comment['reported']);
                if (!$needsReview) {
                    continue;
                }
                if ($query !== '' && !str_contains(mb_strtolower(
                    $post['title'] . ' ' . $comment['author'] . ' ' . $comment['text']
                ), $query)) {
                    continue;
                }
                $comments[] = [
                    'postId' => (int)$post['id'],
                    'postTitle' => (string)$post['title'],
                    'postCreated' => (int)$post['created'],
                    'postStatus' => (string)$post['status'],
                    'postRemovedAt' => isset($post['removedAt']) ? (int)$post['removedAt'] : null,
                    'commentId' => (int)$comment['id'],
                    'author' => (string)$comment['author'],
                    'text' => (string)$comment['text'],
                    'created' => (int)$comment['created'],
                    'reportReason' => (string)($comment['reportReason'] ?? ''),
                    'reportedAt' => isset($comment['reportedAt']) ? (int)$comment['reportedAt'] : null,
                    'reported' => (bool)($comment['reported'] ?? false),
                    'aiFlagged' => (bool)($comment['aiFlagged'] ?? false),
                    'aiNeedsReview' => (bool)($comment['aiNeedsReview'] ?? false),
                    'aiCategories' => is_array($comment['aiCategories'] ?? null) ? $comment['aiCategories'] : [],
                    'aiReviewError' => (bool)($comment['aiReviewError'] ?? false),
                ];
            }
        }
        usort($comments, static fn($a, $b): int => $b['created'] <=> $a['created']);
        return $comments;
    }

    public function reportCountsByDate(int $days = 14): array
    {
        $days = max(1, min(90, $days));
        $history = $this->load(self::REPORT_HISTORY, static fn() => []);
        $today = new DateTimeImmutable('today');
        $results = [];
        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = $today->modify("-{$offset} days");
            $key = $date->format('Y-m-d');
            $results[] = [
                'date' => $key,
                'label' => $date->format('M j'),
                'count' => max(0, (int)($history[$key] ?? 0)),
            ];
        }
        return $results;
    }

    private function recordReport(int $timestamp): void
    {
        $history = $this->load(self::REPORT_HISTORY, static fn() => []);
        $date = date('Y-m-d', intdiv($timestamp, 1000));
        $history[$date] = (int)($history[$date] ?? 0) + 1;
        $this->save(self::REPORT_HISTORY, $history);
    }

    public function trendingTopics(int $limit = 5): array
    {
        $todayStart = (int)strtotime('today') * 1000;
        $stopWords = [
            'about', 'after', 'again', 'any', 'anyone', 'are', 'can', 'could', 'for', 'from',
            'help', 'here', 'how', 'have', 'into', 'just', 'looking', 'please', 'someone',
            'that', 'the', 'this', 'those', 'what', 'when', 'where', 'which', 'who', 'with',
            'would', 'you', 'your',
        ];
        $topics = [];
        foreach ($this->all() as $post) {
            $todayComments = count(array_filter(
                $post['commentList'],
                static fn($comment): bool => (int)$comment['created'] >= $todayStart
            ));
            if ($todayComments === 0) {
                continue;
            }

            $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string)$post['title'])) ?: [];
            foreach (array_unique($words) as $word) {
                if (mb_strlen($word) < 3 || !preg_match('/\p{L}/u', $word) || in_array($word, $stopWords, true)) {
                    continue;
                }
                if (!isset($topics[$word])) {
                    $topics[$word] = ['topic' => $word, 'comments' => 0, 'posts' => 0];
                }
                $topics[$word]['comments'] += $todayComments;
                $topics[$word]['posts']++;
            }
        }

        $topics = array_values($topics);
        usort($topics, static fn($a, $b): int =>
            ($b['comments'] <=> $a['comments'])
            ?: ($b['posts'] <=> $a['posts'])
            ?: strcmp($a['topic'], $b['topic'])
        );
        return array_slice($topics, 0, max(0, $limit));
    }

    public function topLikedToday(int $limit = 5): array
    {
        $todayStart = (int)strtotime('today') * 1000;
        $posts = array_filter($this->all(), static fn($post): bool =>
            $post['status'] === 'approved' && (int)$post['created'] >= $todayStart
        );
        usort($posts, static fn($a, $b): int =>
            ($b['likes'] <=> $a['likes']) ?: ($b['created'] <=> $a['created'])
        );
        return array_map(
            fn($post) => $this->enrich($post),
            array_slice(array_values($posts), 0, max(0, $limit))
        );
    }

    public function counts(): array
    {
        $posts = $this->all();
        $counts = ['toApprove' => 0, 'reported' => 0, 'aiReview' => 0, 'approved' => 0, 'removed' => 0, 'comments' => 0, 'total' => count($posts)];
        foreach ($posts as $post) {
            if ($post['status'] === 'pending' && !$post['reported'] && empty($post['aiNeedsReview'])) {
                $counts['toApprove']++;
            }
            if ($post['reported']) {
                $counts['reported']++;
            }
            if (!empty($post['aiNeedsReview'])) {
                $counts['aiReview']++;
            }
            if (isset($counts[$post['status']])) {
                $counts[$post['status']]++;
            }
            foreach ($post['commentList'] as $comment) {
                $counts['comments']++;
                if (!empty($comment['reported'])) {
                    $counts['reported']++;
                }
                if (!empty($comment['aiNeedsReview'])) {
                    $counts['aiReview']++;
                }
            }
        }
        return $counts;
    }

    public function markReviewed(int $id): void
    {
        $this->update($id, static function (&$post): void {
            if ($post['status'] === 'pending') {
                $post['reviewed'] = true;
            }
        });
    }

    public function setStatus(int $id, string $status, string $moderator): array
    {
        $posts = $this->all();
        foreach ($posts as &$post) {
            if ((int)$post['id'] !== $id) {
                continue;
            }
            if (!in_array($status, ['approved', 'removed'], true)) {
                return ['error' => 'Invalid moderation action.'];
            }
            if ($post['status'] === 'pending' && !$post['reviewed']) {
                return ['error' => 'Open and check this post before you approve or remove it.'];
            }
            $post['status'] = $status;
            $post['aiNeedsReview'] = false;
            $post['aiReviewError'] = false;
            $post['reported'] = false;
            $post['reportReason'] = '';
            $post['reportedAt'] = null;
            $post['removedAt'] = $status === 'removed' ? (int)(microtime(true) * 1000) : null;
            if ($status === 'removed') {
                $post['pinned'] = false;
            }
            $this->save(self::POSTS, $posts);
            $this->log($moderator, $status === 'approved' ? 'Approved' : 'Removed', $post);
            return [];
        }
        unset($post);
        return ['error' => 'Post not found.'];
    }

    public function reportPost(int $id, string $reason): array
    {
        if (!in_array($reason, self::REPORT_REASONS, true)) {
            return ['error' => 'Choose a valid report reason.'];
        }
        $updated = false;
        $timestamp = (int)(microtime(true) * 1000);
        $this->update($id, static function (&$post) use ($reason, $timestamp, &$updated): void {
            if ($post['status'] === 'removed') {
                return;
            }
            if (!empty($post['reported'])) {
                return;
            }
            $post['reported'] = true;
            $post['reportReason'] = $reason;
            $post['reportedAt'] = $timestamp;
            $updated = true;
        });
        if (!$updated) {
            return ['error' => 'This post is already reported or is no longer available.'];
        }
        $this->recordReport($timestamp);
        return [];
    }

    public function clearPostReport(int $id): bool
    {
        $cleared = false;
        $this->update($id, static function (&$post) use (&$cleared): void {
            $cleared = !empty($post['reported']);
            $post['reported'] = false;
            $post['reportReason'] = '';
            $post['reportedAt'] = null;
        });
        return $cleared;
    }

    public function deletePost(int $id, string $moderator): bool
    {
        $posts = $this->all();
        foreach ($posts as $index => $post) {
            if ((int)$post['id'] !== $id) {
                continue;
            }
            if (empty($post['reported'])) {
                return false;
            }
            $this->log($moderator, 'Deleted', $post);
            unset($posts[$index]);
            $this->save(self::POSTS, array_values($posts));
            $this->save(self::SAVED, array_values(array_diff($this->savedIds(), [$id])));
            return true;
        }
        return false;
    }

    public function updatePost(int $id, string $title, string $body, string $tags): array
    {
        $title = trim($title);
        $body = trim($body);
        if (mb_strlen($title) < 5 || mb_strlen($title) > 140) {
            return ['error' => 'The title must be between 5 and 140 characters.'];
        }
        if (mb_strlen($body) > 5000) {
            return ['error' => 'Post text must be 5,000 characters or fewer.'];
        }
        $normalizedTags = [];
        foreach (preg_split('/[,\\n]+/', $tags) ?: [] as $tag) {
            $tag = trim($tag);
            if ($tag === '') {
                continue;
            }
            if (mb_strlen($tag) > 24) {
                return ['error' => 'Each tag must be 24 characters or fewer.'];
            }
            $normalizedTags[] = $tag;
        }
        $normalizedTags = array_values(array_unique($normalizedTags));
        if (count($normalizedTags) > 5) {
            return ['error' => 'A post can have up to five tags.'];
        }

        $updated = false;
        $this->update($id, static function (&$post) use ($title, $body, $normalizedTags, &$updated): void {
            $post['title'] = $title;
            $post['body'] = $body;
            $post['tags'] = $normalizedTags;
            $updated = true;
        });
        return $updated ? [] : ['error' => 'Post not found.'];
    }

    public function toggleLock(int $id): ?bool
    {
        $locked = null;
        $this->update($id, static function (&$post) use (&$locked): void {
            $post['locked'] = !($post['locked'] ?? false);
            $locked = $post['locked'];
        });
        return $locked;
    }

    public function restore(int $id, string $moderator): void
    {
        $found = null;
        $this->update($id, static function (&$post) use (&$found): void {
            $post['status'] = 'pending';
            $post['reviewed'] = false;
            $post['removedAt'] = null;
            $found = $post;
        });
        if ($found !== null) {
            $this->log($moderator, 'Sent back to the queue', $found);
        }
    }

    public function toggleLike(int $id): void
    {
        $this->update($id, static function (&$post): void {
            $post['liked'] = !$post['liked'];
            $post['likes'] += $post['liked'] ? 1 : -1;
        });
    }

    public function togglePin(int $id): void
    {
        $this->update($id, static function (&$post): void {
            if ($post['status'] === 'approved') {
                $post['pinned'] = !$post['pinned'];
            }
        });
    }

    public function toggleSave(int $id): bool
    {
        $ids = $this->savedIds();
        $saved = !in_array($id, $ids, true);
        $ids = $saved ? [...$ids, $id] : array_values(array_diff($ids, [$id]));
        $this->save(self::SAVED, $ids);
        return $saved;
    }

    public function saved(): array
    {
        $ids = $this->savedIds();
        return array_map(fn($post) => $this->enrich($post), array_values(array_filter(
            $this->all(),
            static fn($post) => in_array($post['id'], $ids, true)
        )));
    }

    public function addComment(int $postId, string $author, string $text, ?array $aiReview = null): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }
        $aiNeedsReview = !empty($aiReview['needsReview']);
        $added = false;
        $this->update($postId, static function (&$post) use ($author, $text, $aiReview, $aiNeedsReview, &$added): void {
            if (!empty($post['locked']) || $post['status'] !== 'approved') {
                return;
            }
            $ids = array_column($post['commentList'], 'id');
            $post['commentList'][] = [
                'id' => $ids ? max($ids) + 1 : 1,
                'author' => $author,
                'text' => $text,
                'created' => (int)(microtime(true) * 1000),
                'likes' => 0,
                'hearts' => 0,
                'reported' => false,
                'reportReason' => '',
                'hidden' => $aiNeedsReview,
                'aiFlagged' => (bool)($aiReview['flagged'] ?? false),
                'aiNeedsReview' => $aiNeedsReview,
                'aiCategories' => is_array($aiReview['categories'] ?? null) ? $aiReview['categories'] : [],
                'aiReviewError' => $aiNeedsReview && empty($aiReview['available']),
            ];
            $added = true;
        });
        return $added;
    }

    public function approveAiComment(int $postId, int $commentId): bool
    {
        $approved = false;
        $this->update($postId, static function (&$post) use ($commentId, &$approved): void {
            foreach ($post['commentList'] as &$comment) {
                if ((int)$comment['id'] !== $commentId || empty($comment['aiNeedsReview'])) {
                    continue;
                }
                $comment['aiNeedsReview'] = false;
                $comment['aiReviewError'] = false;
                $comment['hidden'] = false;
                $approved = true;
                break;
            }
            unset($comment);
        });
        return $approved;
    }

    public function commentNeedsAiReview(int $postId, int $commentId): bool
    {
        $post = $this->get($postId);
        if ($post === null) {
            return false;
        }
        foreach ($post['commentList'] as $comment) {
            if ((int)$comment['id'] === $commentId) {
                return !empty($comment['aiNeedsReview']);
            }
        }
        return false;
    }

    public function reportComment(int $postId, int $commentId, string $reason): array
    {
        if (!in_array($reason, self::REPORT_REASONS, true)) {
            return ['error' => 'Choose a valid report reason.'];
        }
        $updated = false;
        $timestamp = (int)(microtime(true) * 1000);
        $this->update($postId, static function (&$post) use ($commentId, $reason, $timestamp, &$updated): void {
            if ($post['status'] === 'removed') {
                return;
            }
            foreach ($post['commentList'] as &$comment) {
                if ((int)$comment['id'] !== $commentId || !empty($comment['reported']) || !empty($comment['hidden'])) {
                    continue;
                }
                $comment['reported'] = true;
                $comment['reportReason'] = $reason;
                $comment['reportedAt'] = $timestamp;
                $updated = true;
                break;
            }
            unset($comment);
        });
        if (!$updated) {
            return ['error' => 'This comment is already reported or is no longer available.'];
        }
        $this->recordReport($timestamp);
        return [];
    }

    public function clearCommentReport(int $postId, int $commentId): bool
    {
        $cleared = false;
        $this->update($postId, static function (&$post) use ($commentId, &$cleared): void {
            foreach ($post['commentList'] as &$comment) {
                if ((int)$comment['id'] === $commentId) {
                    $cleared = !empty($comment['reported']);
                    $comment['reported'] = false;
                    $comment['reportReason'] = '';
                    $comment['reportedAt'] = null;
                    break;
                }
            }
            unset($comment);
        });
        return $cleared;
    }

    public function hideComment(int $postId, int $commentId, bool $hidden = true): bool
    {
        $updated = false;
        $this->update($postId, static function (&$post) use ($commentId, $hidden, &$updated): void {
            foreach ($post['commentList'] as &$comment) {
                if ((int)$comment['id'] !== $commentId) {
                    continue;
                }
                $comment['hidden'] = $hidden;
                if ($hidden) {
                    $comment['reported'] = false;
                    $comment['reportReason'] = '';
                    $comment['reportedAt'] = null;
                    $comment['aiNeedsReview'] = false;
                }
                $updated = true;
                break;
            }
            unset($comment);
        });
        return $updated;
    }

    public function removeComment(int $postId, int $commentId): void
    {
        $this->update($postId, static function (&$post) use ($commentId): void {
            $post['commentList'] = array_values(array_filter(
                $post['commentList'],
                static fn($comment) => (int)$comment['id'] !== $commentId
            ));
        });
    }

    public function reactComment(int $postId, int $commentId, string $reaction): void
    {
        if (!in_array($reaction, ['likes', 'hearts'], true)) {
            return;
        }
        $this->update($postId, static function (&$post) use ($commentId, $reaction): void {
            foreach ($post['commentList'] as &$comment) {
                if ((int)$comment['id'] === $commentId && empty($comment['hidden'])) {
                    $comment[$reaction]++;
                    break;
                }
            }
            unset($comment);
        });
    }

    public function comments(): array
    {
        $comments = [];
        foreach ($this->all() as $post) {
            foreach ($post['commentList'] as $comment) {
                $comment['postId'] = $post['id'];
                $comment['postTitle'] = $post['title'];
                $comment['postCreated'] = (int)$post['created'];
                $comment['postStatus'] = (string)$post['status'];
                $comment['postRemovedAt'] = isset($post['removedAt']) ? (int)$post['removedAt'] : null;
                $comment['reported'] = (bool)($comment['reported'] ?? false);
                $comment['reportReason'] = (string)($comment['reportReason'] ?? '');
                $comment['reportedAt'] = isset($comment['reportedAt']) ? (int)$comment['reportedAt'] : null;
                $comment['hidden'] = (bool)($comment['hidden'] ?? false);
                $comment['aiFlagged'] = (bool)($comment['aiFlagged'] ?? false);
                $comment['aiNeedsReview'] = (bool)($comment['aiNeedsReview'] ?? false);
                $comment['aiCategories'] = is_array($comment['aiCategories'] ?? null) ? $comment['aiCategories'] : [];
                $comment['aiReviewError'] = (bool)($comment['aiReviewError'] ?? false);
                $comments[] = $comment;
            }
        }
        usort($comments, static fn($a, $b) => $b['created'] <=> $a['created']);
        return $comments;
    }

    public function add(array $data, string $author, array $communitySlugs, ?array $aiReview = null): array
    {
        $community = trim((string)($data['community'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));
        $body = trim((string)($data['body'] ?? ''));
        if (!in_array($community, $communitySlugs, true)) {
            return ['error' => 'Choose a community to post in.'];
        }
        if (mb_strlen($title) < 5) {
            return ['error' => 'Title must be at least 5 characters.'];
        }
        if (mb_strlen($body) > 5000) {
            return ['error' => 'Post text must be 5,000 characters or fewer.'];
        }
        $tags = [];
        foreach (preg_split('/[,\\n]+/', (string)($data['tags'] ?? '')) ?: [] as $tag) {
            $tag = trim($tag);
            if ($tag === '') {
                continue;
            }
            if (mb_strlen($tag) > 24) {
                return ['error' => 'Each tag must be 24 characters or fewer.'];
            }
            $tags[] = $tag;
        }
        $tags = array_values(array_unique($tags));
        if (count($tags) > 5) {
            return ['error' => 'A post can have up to five tags.'];
        }
        $aiNeedsReview = !empty($aiReview['needsReview']);
        $posts = $this->all();
        $ids = array_column($posts, 'id');
        $id = $ids ? max($ids) + 1 : 1;
        $posts[] = [
            'id' => $id,
            'author' => $author,
            'community' => $community,
            'title' => $title,
            'body' => $body,
            'image' => (string)($data['image'] ?? '') ?: $this->art($id),
            'created' => (int)(microtime(true) * 1000),
            'removedAt' => null,
            'likes' => 0,
            'liked' => false,
            'pinned' => false,
            'locked' => false,
            'tags' => $tags,
            'status' => $aiNeedsReview ? 'pending' : 'approved',
            'reported' => false,
            'reportReason' => '',
            'reviewed' => !$aiNeedsReview,
            'aiFlagged' => (bool)($aiReview['flagged'] ?? false),
            'aiNeedsReview' => $aiNeedsReview,
            'aiCategories' => is_array($aiReview['categories'] ?? null) ? $aiReview['categories'] : [],
            'aiReviewError' => $aiNeedsReview && empty($aiReview['available']),
            'commentList' => [],
        ];
        $this->save(self::POSTS, $posts);
        $this->log($author, 'Posted', end($posts));
        return ['id' => $id];
    }

    public function activity(int $limit = 8): array
    {
        return array_slice($this->load(self::LOG, static fn() => []), 0, $limit);
    }

    public function reset(): void
    {
        $this->save(self::POSTS, $this->seedPosts());
    }
}
