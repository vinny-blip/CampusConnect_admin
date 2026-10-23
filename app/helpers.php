<?php
declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ago(int $timestamp): string
{
    $minutes = max(1, (int)round(((int)(microtime(true) * 1000) - $timestamp) / 60000));
    if ($minutes < 60) {
        return $minutes . 'm';
    }
    if ($minutes < 1440) {
        return (int)floor($minutes / 60) . 'h';
    }
    return (int)floor($minutes / 1440) . 'd';
}

function date_label(int $timestamp): string
{
    return date('M j, Y', intdiv($timestamp, 1000));
}

function date_time_label(int $timestamp): string
{
    return date('M j, Y g:i A', intdiv($timestamp, 1000));
}

function initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach ($words as $word) {
        $letters .= mb_substr($word, 0, 1);
    }
    return mb_strtoupper(mb_substr($letters, 0, 2));
}

function icon_svg(string $name): string
{
    $paths = [
        'home' => '<path d="m3 10 9-7 9 7"/><path d="M5 9v11h14V9"/><path d="M9 20v-6h6v6"/>',
        'queue' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'sliders' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3"/><path d="M2 14h4M10 8h4M18 16h4"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.2 1.8c-1.1 1-1.8 1.3-1.8 2.7M12 17h.01"/>',
        'message' => '<path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8A8.5 8.5 0 0 1 8.7 3.9a8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8z"/>',
        'heart-filled' => '<path fill="currentColor" stroke="none" d="m12 21-1.5-1.4C5.2 14.8 2 11.9 2 8.4A5.4 5.4 0 0 1 7.5 3 6 6 0 0 1 12 5a6 6 0 0 1 4.5-2A5.4 5.4 0 0 1 22 8.4c0 3.5-3.2 6.4-8.5 11.2z"/>',
        'thumb-up' => '<path d="M7 10v11H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2zM7 10l5-8a3 3 0 0 1 2 3v5h5a2 2 0 0 1 2 2l-2 7a2 2 0 0 1-2 2H7"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
        'back' => '<path d="m15 18-6-6 6-6"/><path d="M20 12H9"/>',
        'bookmark' => '<path d="M6 4h12v17l-6-4-6 4z"/>',
        'bookmark-filled' => '<path fill="currentColor" d="M6 4h12v17l-6-4-6 4z"/>',
        'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z"/>',
        'star-filled' => '<path fill="currentColor" d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z"/>',
        'pin' => '<path d="m16 3 5 5-4 1-4 4 1 4-2 2-3-5-5-3 2-2 4 1 4-4z"/><path d="m2 22 7-7"/>',
    ];
    if (!isset($paths[$name])) {
        throw new InvalidArgumentException('Unknown icon: ' . $name);
    }
    return '<svg class="web-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}

function report_reason_options(): array
{
    return [
        'Non-academic content',
        'Toxic or harassing content',
        'Plagiarized content',
        'Other guideline violation',
    ];
}

function flash_message(?array $flash): void
{
    if ($flash === null) {
        return;
    }
    $type = ($flash['type'] ?? '') === 'error' ? 'error' : 'success';
    echo '<p class="notice ' . $type . '" role="status">' . h($flash['message'] ?? '') . '</p>';
}

function post_action(string $csrf, string $action, int $postId, string $label, string $class = 'chip', bool $disabled = false, ?string $icon = null): void
{
    echo '<form class="inline-action" method="post">' . $csrf . '<input type="hidden" name="action" value="' . h($action)
        . '"><input type="hidden" name="post_id" value="' . $postId
        . '"><button class="' . h($class) . '" type="submit"' . ($disabled ? ' disabled' : '') . '>'
        . ($icon === null ? '' : icon_svg($icon)) . h($label) . '</button></form>';
}
