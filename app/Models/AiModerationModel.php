<?php
declare(strict_types=1);

final class AiModerationModel
{
    public function __construct(private PDO $db)
    {
    }

    public function enabled(): bool
    {
        $enabled = $this->db->query('SELECT enabled FROM ai_moderation_settings WHERE id = 1')->fetchColumn();
        return $enabled !== false && (bool)$enabled;
    }

    public function configured(): bool
    {
        return trim((string)getenv('OPENAI_API_KEY')) !== '';
    }

    public function setEnabled(bool $enabled): void
    {
        $upsert = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'ON CONFLICT(id) DO UPDATE SET enabled = excluded.enabled'
            : 'ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)';
        $statement = $this->db->prepare(
            'INSERT INTO ai_moderation_settings (id, enabled) VALUES (1, ?) ' . $upsert
        );
        $statement->execute([$enabled ? 1 : 0]);
    }

    public function inspect(string $text, string $image = ''): array
    {
        $apiKey = trim((string)getenv('OPENAI_API_KEY'));
        if ($apiKey === '') {
            return ['available' => false, 'flagged' => false, 'categories' => [], 'scores' => []];
        }

        $input = [['type' => 'text', 'text' => trim($text)]];
        if (preg_match('/^data:image\/(?:jpeg|png|gif|webp);base64,/', $image)) {
            $input[] = ['type' => 'image_url', 'image_url' => ['url' => $image]];
        }

        try {
            $payload = json_encode([
                'model' => 'omni-moderation-latest',
                'input' => $input,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return ['available' => false, 'flagged' => false, 'categories' => [], 'scores' => []];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer {$apiKey}\r\nContent-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents('https://api.openai.com/v1/moderations', false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                $status = (int)$matches[1];
            }
        }
        if ($response === false || $status < 200 || $status >= 300) {
            return ['available' => false, 'flagged' => false, 'categories' => [], 'scores' => []];
        }

        try {
            $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR)['results'][0] ?? null;
        } catch (JsonException) {
            $result = null;
        }
        if (!is_array($result) || !is_array($result['categories'] ?? null)) {
            return ['available' => false, 'flagged' => false, 'categories' => [], 'scores' => []];
        }

        $categories = array_keys(array_filter($result['categories'], static fn($flagged) => $flagged === true));
        return [
            'available' => true,
            'flagged' => (bool)($result['flagged'] ?? false),
            'categories' => $categories,
            'scores' => is_array($result['category_scores'] ?? null) ? $result['category_scores'] : [],
        ];
    }
}
