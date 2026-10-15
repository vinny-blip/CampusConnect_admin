<?php
declare(strict_types=1);

final class InboxModel extends StateModel
{
    private const KEY = 'messages';

    private function all(): array
    {
        return $this->load(self::KEY, static function (): array {
            $now = (int)(microtime(true) * 1000);
            $message = static function (string $from, string $text, int $minutes) use ($now): array {
                return ['from' => $from, 'text' => $text, 't' => $now - $minutes * 60000];
            };
            return [
                ['id' => 1, 'box' => 'mod', 'with' => 'anonymous12', 'subject' => 'Why was my post removed?', 'unread' => true, 'messages' => [$message('anonymous12', 'Hi mods, my post about Calculus notes was removed. Can you tell me why?', 90)]],
                ['id' => 2, 'box' => 'mod', 'with' => 'maria.santos', 'subject' => 'Starting a study-group community', 'unread' => true, 'messages' => [$message('maria.santos', 'Can we open a community for study groups? I can help moderate it.', 240)]],
                ['id' => 3, 'box' => 'mod', 'with' => 'juan.delacruz', 'subject' => "Spam in IT'ers", 'unread' => false, 'messages' => [$message('juan.delacruz', "There is a lot of advertising in the IT'ers feed lately.", 600), $message('me', 'Thanks, we are checking the reported posts.', 540)]],
                ['id' => 4, 'box' => 'dm', 'with' => 'Maria Santos', 'subject' => '', 'unread' => true, 'messages' => [$message('Maria Santos', 'Hi! Are you joining the moderators meeting tomorrow?', 45)]],
                ['id' => 5, 'box' => 'dm', 'with' => 'Juan Dela Cruz', 'subject' => '', 'unread' => false, 'messages' => [$message('Juan Dela Cruz', 'Please check the reported posts in Engineering.', 300), $message('me', 'On it.', 280)]],
                ['id' => 6, 'box' => 'dm', 'with' => 'HANDA Official', 'subject' => '', 'unread' => true, 'messages' => [$message('HANDA Official', 'Reminder: please pin the evacuation notice for today.', 20)]],
            ];
        });
    }

    public function get(int $id): ?array
    {
        foreach ($this->all() as $thread) {
            if ((int)$thread['id'] === $id) {
                return $thread;
            }
        }
        return null;
    }

    public function threads(string $box): array
    {
        $threads = array_values(array_filter($this->all(), static fn($thread) => $thread['box'] === $box));
        usort($threads, static fn($a, $b) => $b['messages'][count($b['messages']) - 1]['t'] <=> $a['messages'][count($a['messages']) - 1]['t']);
        return $threads;
    }

    public function markRead(int $id): void
    {
        $all = $this->all();
        foreach ($all as &$thread) {
            if ((int)$thread['id'] === $id) {
                $thread['unread'] = false;
                break;
            }
        }
        unset($thread);
        $this->save(self::KEY, $all);
    }

    public function reply(int $id, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        $all = $this->all();
        foreach ($all as &$thread) {
            if ((int)$thread['id'] === $id) {
                $thread['messages'][] = ['from' => 'me', 'text' => $text, 't' => (int)(microtime(true) * 1000)];
                break;
            }
        }
        unset($thread);
        $this->save(self::KEY, $all);
    }

    public function create(string $box, string $who, string $subject, string $text): array
    {
        $who = trim($who);
        $subject = trim($subject);
        $text = trim($text);
        if ($who === '') {
            return ['error' => 'Enter who the message is for.'];
        }
        if ($text === '') {
            return ['error' => 'Write a message first.'];
        }
        if ($box === 'mod' && $subject === '') {
            return ['error' => 'Add a subject.'];
        }
        $all = $this->all();
        $ids = array_column($all, 'id');
        $id = $ids ? max($ids) + 1 : 1;
        $all[] = [
            'id' => $id, 'box' => $box, 'with' => $who, 'subject' => $subject,
            'unread' => false,
            'messages' => [['from' => 'me', 'text' => $text, 't' => (int)(microtime(true) * 1000)]],
        ];
        $this->save(self::KEY, $all);
        return ['id' => $id];
    }

    public function unread(?string $box = null): int
    {
        return count(array_filter($this->all(), static fn($thread) => $thread['unread'] && (!$box || $thread['box'] === $box)));
    }
}
