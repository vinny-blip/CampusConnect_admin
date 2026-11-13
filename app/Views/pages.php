<?php
// Selects the page template that matches the route handled by the controller.
switch ($page):
    case 'home':
        require ($user['role'] ?? '') === 'admin'
            ? __DIR__ . '/pages/moderator-accounts.php'
            : __DIR__ . '/pages/mobile-list.php';
        break;
    case 'posts':
    case 'control':
    case 'reported':
    case 'removed':
    case 'dismissed':
    case 'comments':
    case 'users':
        require __DIR__ . '/pages/mobile-list.php';
        break;
    case 'logs':
        require ($user['role'] ?? '') === 'admin'
            ? __DIR__ . '/pages/moderator-logs.php'
            : __DIR__ . '/pages/logs.php';
        break;
    case 'moderator-activity':
        require __DIR__ . '/pages/moderator-activity.php';
        break;
    case 'reports-overview':
        require __DIR__ . '/pages/reports-overview.php';
        break;
    case 'user-accounts':
        require __DIR__ . '/pages/user-accounts.php';
        break;
    case 'user':
        require __DIR__ . '/pages/user-activity.php';
        break;
    case 'search':
        require __DIR__ . '/pages/feed.php';
        break;
    case 'post':
        require __DIR__ . '/pages/post.php';
        break;
    case 'overview':
        require __DIR__ . '/pages/overview.php';
        break;
    case 'notifications':
        require __DIR__ . '/pages/notifications.php';
        break;
    case 'profile':
        require __DIR__ . '/pages/profile.php';
        break;
    case 'about':
        require __DIR__ . '/pages/about.php';
        break;
    case 'faqs':
        require __DIR__ . '/pages/faqs.php';
        break;
    case 'mobile-feed':
    case 'mobile-queues':
    case 'mobile-reports':
    case 'mobile-comments':
        require __DIR__ . '/pages/mobile-list.php';
        break;
    case 'mobile-post':
        require __DIR__ . '/pages/mobile-post.php';
        break;
    default:
        echo '<h2>Page not found</h2>';
endswitch;
