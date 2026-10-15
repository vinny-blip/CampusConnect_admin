<?php
declare(strict_types=1);

final class CommunityModel extends StateModel
{
    private const KEY = 'communities';

    private function all(): array
    {
        return $this->load(self::KEY, static fn() => [
            ['slug' => 'itters', 'name' => "IT'ers", 'visitors' => '2k', 'contrib' => 200, 'starred' => true, 'mod' => true, 'request' => null],
            ['slug' => 'engineering', 'name' => "Engineering'ers", 'visitors' => '2.5k', 'contrib' => 90, 'starred' => false, 'mod' => true, 'request' => null],
            ['slug' => 'educers', 'name' => "Educ'ers", 'visitors' => '1k', 'contrib' => 112, 'starred' => false, 'mod' => true, 'request' => null],
            ['slug' => 'cbaers', 'name' => "CBA'ers", 'visitors' => '3.1k', 'contrib' => 112, 'starred' => false, 'mod' => true, 'request' => null],
            ['slug' => 'designatmobbin', 'name' => 'r/ designatmobbin', 'visitors' => '1.2k', 'contrib' => 64, 'starred' => false, 'mod' => true, 'request' => null],
        ]);
    }

    public function list(): array
    {
        return array_values(array_filter($this->all(), static fn($community) => $community['mod']));
    }

    public function get(string $slug): ?array
    {
        foreach ($this->all() as $community) {
            if ($community['slug'] === $slug) {
                return $community;
            }
        }
        return null;
    }

    public function name(string $slug): string
    {
        return $this->get($slug)['name'] ?? $slug;
    }

    public function search(string $query): array
    {
        $query = mb_strtolower(trim($query));
        return $query === '' ? [] : array_values(array_filter(
            $this->all(),
            static fn($community) => str_contains(mb_strtolower($community['name']), $query)
        ));
    }

    public function change(string $slug, string $action): void
    {
        $all = $this->all();
        foreach ($all as &$community) {
            if ($community['slug'] !== $slug) {
                continue;
            }
            if ($action === 'star') {
                $community['starred'] = !$community['starred'];
            } elseif ($action === 'leave') {
                $community['mod'] = false;
                $community['starred'] = false;
            } elseif (in_array($action, ['Alumni', 'Advisor'], true)) {
                $community['request'] = $action;
            }
            break;
        }
        unset($community);
        $this->save(self::KEY, $all);
    }
}
