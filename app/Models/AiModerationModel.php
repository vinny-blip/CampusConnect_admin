<?php
declare(strict_types=1);

final class AiModerationModel extends StateModel
{
    private const KEY = 'ai_moderation';

    public function enabled(): bool
    {
        $settings = $this->load(self::KEY, static fn() => ['enabled' => false]);
        return (bool)($settings['enabled'] ?? false);
    }

    public function setEnabled(bool $enabled): void
    {
        $this->save(self::KEY, ['enabled' => $enabled]);
    }
}
