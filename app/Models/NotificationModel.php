<?php
declare(strict_types=1);

final class NotificationModel extends StateModel
{
    private const KEY = 'notifications';

    /** Loads all notifications saved for the current dashboard account. */
    private function all(): array
    {
        return $this->load(self::KEY, static fn() => []);
    }

    /** Lists notifications with the newest items first. */
    public function list(): array
    {
        $items = $this->all();
        usort($items, static fn($a, $b) => $b['t'] <=> $a['t']);
        return $items;
    }

    /** Counts notifications that have not been read. */
    public function unread(): int
    {
        return count(array_filter($this->all(), static fn($item) => !$item['read']));
    }

    /** Marks one notification as read and returns it if it exists. */
    public function markRead(int $id): ?array
    {
        $all = $this->all();
        $found = null;
        foreach ($all as &$item) {
            if ((int)$item['id'] === $id) {
                $item['read'] = true;
                $found = $item;
                break;
            }
        }
        unset($item);
        $this->save(self::KEY, $all);
        return $found;
    }

    /** Marks every saved notification as read. */
    public function markAllRead(): void
    {
        $all = $this->all();
        foreach ($all as &$item) {
            $item['read'] = true;
        }
        unset($item);
        $this->save(self::KEY, $all);
    }

    /** Adds a new unread notification. */
    public function add(string $text, string $link): void
    {
        $all = $this->all();
        $ids = array_column($all, 'id');
        $all[] = [
            'id' => $ids ? max($ids) + 1 : 1,
            'text' => $text,
            'link' => $link,
            't' => (int)(microtime(true) * 1000),
            'read' => false,
        ];
        $this->save(self::KEY, $all);
    }

    /** Adds a notification only if its link is not already present. */
    public function addOnce(string $text, string $link): void
    {
        $this->addManyOnce([['text' => $text, 'link' => $link]]);
    }

    /** Adds or updates notifications without duplicating their links. */
    public function addManyOnce(array $items): void
    {
        $all = $this->all();
        $linkIndexes = [];
        foreach ($all as $index => $existing) {
            if (isset($existing['link'])) {
                $linkIndexes[$existing['link']] = $index;
            }
        }
        $ids = array_map('intval', array_column($all, 'id'));
        $nextId = $ids ? max($ids) + 1 : 1;
        $changed = false;

        foreach ($items as $item) {
            $text = (string)($item['text'] ?? '');
            $link = (string)($item['link'] ?? '');
            if ($text === '' || $link === '') {
                continue;
            }
            if (isset($linkIndexes[$link])) {
                $index = $linkIndexes[$link];
                if (($all[$index]['text'] ?? '') !== $text) {
                    $all[$index]['text'] = $text;
                    $changed = true;
                }
                continue;
            }
            $all[] = [
                'id' => $nextId++,
                'text' => $text,
                'link' => $link,
                't' => (int)(microtime(true) * 1000),
                'read' => false,
            ];
            $linkIndexes[$link] = count($all) - 1;
            $changed = true;
        }

        if ($changed) {
            $this->save(self::KEY, $all);
        }
    }
}
