<?php
declare(strict_types=1);

final class NotificationModel extends StateModel
{
    private const KEY = 'notifications';

    private function all(): array
    {
        return $this->load(self::KEY, static fn() => []);
    }

    public function list(): array
    {
        $items = $this->all();
        usort($items, static fn($a, $b) => $b['t'] <=> $a['t']);
        return $items;
    }

    public function unread(): int
    {
        return count(array_filter($this->all(), static fn($item) => !$item['read']));
    }

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

    public function markAllRead(): void
    {
        $all = $this->all();
        foreach ($all as &$item) {
            $item['read'] = true;
        }
        unset($item);
        $this->save(self::KEY, $all);
    }

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
}
