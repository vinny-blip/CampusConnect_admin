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

function initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach ($words as $word) {
        $letters .= mb_substr($word, 0, 1);
    }
    return mb_strtoupper(mb_substr($letters, 0, 2));
}

function community_url(string $slug): string
{
    return $slug === 'designatmobbin'
        ? 'designatmobbin.php'
        : 'community.php?c=' . rawurlencode($slug);
}

function flash_message(?array $flash): void
{
    if ($flash === null) {
        return;
    }
    $type = ($flash['type'] ?? '') === 'error' ? 'error' : 'success';
    echo '<p class="notice ' . $type . '" role="status">' . h($flash['message'] ?? '') . '</p>';
}

function post_action(string $csrf, string $action, int $postId, string $label, string $class = 'chip', bool $disabled = false): void
{
    echo '<form class="inline-action" method="post"><input type="hidden" name="csrf_token" value="'
        . h($csrf) . '"><input type="hidden" name="action" value="' . h($action)
        . '"><input type="hidden" name="post_id" value="' . $postId
        . '"><button class="' . h($class) . '" type="submit"' . ($disabled ? ' disabled' : '') . '>'
        . h($label) . '</button></form>';
}
