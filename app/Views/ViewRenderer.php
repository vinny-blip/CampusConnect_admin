<?php
declare(strict_types=1);

final class ViewRenderer
{
    public function render(string $page, array $data, ?array $flash, string $csrf): void
    {
        extract($data, EXTR_SKIP);
        $page = $page;

        if (in_array($page, ['login', 'register'], true)) {
            require __DIR__ . '/auth.php';
            return;
        }
        require __DIR__ . '/layout.php';
    }
}
