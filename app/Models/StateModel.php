<?php
declare(strict_types=1);

class StateModel
{
    public function __construct(
        protected PDO $db,
        protected int $userId
    ) {
    }

    protected function load(string $key, callable $seed): array
    {
        $statement = $this->db->prepare('SELECT state_data FROM app_state WHERE user_id = ? AND state_key = ?');
        $statement->execute([$this->userId, $key]);
        $data = $statement->fetchColumn();
        if ($data !== false) {
            $decoded = json_decode((string)$data, true);
            if (!is_array($decoded)) {
                throw new RuntimeException("Stored data for {$key} is invalid.");
            }
            return $decoded;
        }

        $initial = $seed();
        $this->save($key, $initial);
        return $initial;
    }

    protected function save(string $key, array $data): void
    {
        $upsert = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'ON CONFLICT(user_id, state_key) DO UPDATE SET state_data = excluded.state_data'
            : 'ON DUPLICATE KEY UPDATE state_data = VALUES(state_data)';
        $statement = $this->db->prepare(
            'INSERT INTO app_state (user_id, state_key, state_data) VALUES (?, ?, ?) ' . $upsert
        );
        $statement->execute([
            $this->userId,
            $key,
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
