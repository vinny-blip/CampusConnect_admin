<?php
declare(strict_types=1);

final class RestrictedModel extends StateModel
{
    private const KEY = 'restricted';

    private function all(): array
    {
        return $this->load(self::KEY, static function (): array {
            $now = (int)(microtime(true) * 1000);
            return [
                ['username' => 'anonymous7', 'reason' => "Repeated spam in IT'ers", 't' => $now - 4000 * 60000],
                ['username' => 'anonymous19', 'reason' => 'Harassment in comments', 't' => $now - 9000 * 60000],
                ['username' => 'anonymous33', 'reason' => 'Selling items without permission', 't' => $now - 15000 * 60000],
            ];
        });
    }

    public function list(): array
    {
        $items = $this->all();
        usort($items, static fn($a, $b) => $b['t'] <=> $a['t']);
        return $items;
    }

    public function add(string $username, string $reason): ?string
    {
        $username = trim($username);
        $reason = trim($reason);
        if ($username === '') {
            return 'Enter a username to restrict.';
        }
        if ($reason === '') {
            return 'Add a reason so other moderators know why.';
        }
        $all = $this->all();
        foreach ($all as $item) {
            if (mb_strtolower($item['username']) === mb_strtolower($username)) {
                return "{$username} is already restricted.";
            }
        }
        $all[] = ['username' => $username, 'reason' => $reason, 't' => (int)(microtime(true) * 1000)];
        $this->save(self::KEY, $all);
        return null;
    }

    public function remove(string $username): void
    {
        $all = array_values(array_filter($this->all(), static fn($item) => $item['username'] !== $username));
        $this->save(self::KEY, $all);
    }
}
