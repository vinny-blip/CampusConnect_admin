<?php
declare(strict_types=1);

final class PostModel extends StateModel
{
    private const POSTS = 'posts';
    private const SAVED = 'saved';
    private const LOG = 'log';
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
    private const REASONS = [
        'Spam or advertising', 'Harassment or bullying', 'Misleading information',
        'Off-topic for this community', 'Inappropriate content', 'Selling without permission',
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
        return $this->load(self::POSTS, fn() => $this->seedPosts());
    }

    private function seedPosts(): array
    {
        $posts = [];
        foreach (range(0, 39) as $i) {
            $status = $i % 5 === 4 ? 'approved' : 'pending';
            $reported = $status === 'pending' && $i % 6 === 0;
            $commentCount = $i === 0 ? 3 : $i % 5;
            $comments = [];
            for ($k = 0; $k < $commentCount; $k++) {
                $source = self::COMMENTS[($i + $k) % count(self::COMMENTS)];
                $comments[] = [
                    'id' => $k + 1,
                    'author' => $source[0],
                    'text' => $source[1],
                    'created' => $this->ago(15 + $k * 20),
                    'likes' => ($k * 5 + $i) % 15,
                    'hearts' => ($k * 3 + $i) % 12,
                ];
            }
            $posts[] = [
                'id' => $i + 1,
                'author' => 'anonymous' . ($i + 1),
                'community' => self::SLUGS[$i % count(self::SLUGS)],
                'title' => $i === 0 ? 'Cherish the moments with your friends!' : self::TITLES[$i % count(self::TITLES)],
                'body' => $i === 0
                    ? 'Take a moment today to thank the people who made this semester easier. Share a memory with your friends in the comments.'
                    : 'Posted by a student. Replies and notes from classmates are welcome in the comments.',
                'image' => $this->art($i),
                'created' => $this->ago(5 + $i * 13),
                'likes' => ($i * 7) % 45 + 3,
                'liked' => false,
                'pinned' => false,
                'status' => $status,
                'reported' => $reported,
                'reportReason' => $reported ? self::REASONS[(int)($i / 6) % count(self::REASONS)] : '',
                'reviewed' => false,
                'commentList' => $comments,
            ];
        }
        $this->save(self::SAVED, []);
        $this->save(self::LOG, []);
        return $posts;
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
                && ($query === '' || str_contains(mb_strtolower($post['title'] . ' ' . $post['author'] . ' ' . $post['community']), $query));
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
        return $this->find('pending', null, $query, $kind === 'reported');
    }

    public function counts(): array
    {
        $posts = $this->all();
        $counts = ['toApprove' => 0, 'reported' => 0, 'approved' => 0, 'removed' => 0, 'comments' => 0, 'total' => count($posts)];
        foreach ($posts as $post) {
            if ($post['status'] === 'pending' && !$post['reported']) {
                $counts['toApprove']++;
            }
            if ($post['status'] === 'pending' && $post['reported']) {
                $counts['reported']++;
            }
            if (isset($counts[$post['status']])) {
                $counts[$post['status']]++;
            }
            $counts['comments'] += count($post['commentList']);
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

    public function restore(int $id, string $moderator): void
    {
        $found = null;
        $this->update($id, static function (&$post) use (&$found): void {
            $post['status'] = 'pending';
            $post['reviewed'] = false;
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

    public function addComment(int $postId, string $author, string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }
        return $this->update($postId, static function (&$post) use ($author, $text): void {
            $ids = array_column($post['commentList'], 'id');
            $post['commentList'][] = [
                'id' => $ids ? max($ids) + 1 : 1,
                'author' => $author,
                'text' => $text,
                'created' => (int)(microtime(true) * 1000),
                'likes' => 0,
                'hearts' => 0,
            ];
        });
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
                if ((int)$comment['id'] === $commentId) {
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
                $comments[] = $comment;
            }
        }
        usort($comments, static fn($a, $b) => $b['created'] <=> $a['created']);
        return $comments;
    }

    public function add(array $data, string $author): array
    {
        $community = trim((string)($data['community'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));
        if (!in_array($community, self::SLUGS, true)) {
            return ['error' => 'Choose a community to post in.'];
        }
        if (mb_strlen($title) < 5) {
            return ['error' => 'Title must be at least 5 characters.'];
        }
        $posts = $this->all();
        $ids = array_column($posts, 'id');
        $id = $ids ? max($ids) + 1 : 1;
        $posts[] = [
            'id' => $id,
            'author' => $author,
            'community' => $community,
            'title' => $title,
            'body' => trim((string)($data['body'] ?? '')),
            'image' => (string)($data['image'] ?? '') ?: $this->art($id),
            'created' => (int)(microtime(true) * 1000),
            'likes' => 0,
            'liked' => false,
            'pinned' => false,
            'status' => 'approved',
            'reported' => false,
            'reportReason' => '',
            'reviewed' => true,
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
