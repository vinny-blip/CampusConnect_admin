<?php
switch ($page):
    case 'home':
    case 'posts':
    case 'control':
    case 'reported':
    case 'removed':
    case 'comments':
    case 'users':
        require __DIR__ . '/pages/mobile-list.php';
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
    case 'restricted':
        require __DIR__ . '/pages/restricted.php';
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
