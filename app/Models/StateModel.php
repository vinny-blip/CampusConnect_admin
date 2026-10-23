<?php
declare(strict_types=1);

class StateModel
{
    public function __construct(
        protected PDO $db,
        protected int $userId
    ) {
    }

    protected function load(string $key, callable $initializer): array
    {
        $statement = $this->db->prepare('SELECT state_data FROM admin_app_state WHERE user_id = ? AND state_key = ?');
        $statement->execute([$this->userId, $key]);
        $data = $statement->fetchColumn();
        if ($data !== false) {
            $decoded = json_decode((string)$data, true);
            if (!is_array($decoded)) {
                throw new RuntimeException("Stored data for {$key} is invalid.");
            }
            return $decoded;
        }

        $initial = $initializer();
        $this->save($key, $initial);
        return $initial;
    }

    protected function save(string $key, array $data): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO admin_app_state (user_id, state_key, state_data) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE state_data = VALUES(state_data)'
        );
        $statement->execute([
            $this->userId,
            $key,
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
