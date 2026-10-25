<?php
declare(strict_types=1);

final class AiModerationModel
{
    /** Shares the database connection for AI moderation settings. */
    public function __construct(private PDO $db)
    {
    }

    /** Checks whether AI moderation is enabled in dashboard settings. */
    public function enabled(): bool
    {
        $enabled = $this->db->query('SELECT enabled FROM admin_ai_moderation_settings WHERE id = 1')->fetchColumn();
        return $enabled !== false && (bool)$enabled;
    }

    /** Checks whether an OpenAI API key is available. */
    public function configured(): bool
    {
        return $this->apiKey() !== '';
    }

    /** Saves whether AI moderation is enabled. */
    public function setEnabled(bool $enabled): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO admin_ai_moderation_settings (id, enabled) VALUES (1, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)'
        );
        $statement->execute([$enabled ? 1 : 0]);
    }

    /** Sends text and an optional image for moderation and returns its result. */
    public function inspect(string $text, string $image = ''): array
    {
        $apiKey = $this->apiKey();
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
                'header' => "Authorization: Bearer {$apiKey}\r\n"
                    . "Content-Type: application/json\r\n",
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

    /** Finds the API key in the supported environment or local configuration. */
    private function apiKey(): string
    {
        $key = getenv('OPENAI_API_KEY');
        if (is_string($key) && trim($key) !== '' && trim($key) !== 'PASTE_YOUR_OPENAI_API_KEY_HERE') {
            return trim($key);
        }

        $serverKey = $_SERVER['OPENAI_API_KEY'] ?? '';
        if (is_string($serverKey) && trim($serverKey) !== '' && trim($serverKey) !== 'PASTE_YOUR_OPENAI_API_KEY_HERE') {
            return trim($serverKey);
        }

        $localConfig = dirname(__DIR__, 2) . '/config/openai.local.php';
        if (is_file($localConfig)) {
            $fileKey = require $localConfig;
            if (is_string($fileKey) && trim($fileKey) !== '' && trim($fileKey) !== 'PASTE_YOUR_OPENAI_API_KEY_HERE') {
                return trim($fileKey);
            }
        }

        return '';
    }
}
