<?php
switch ($page):
    case 'home':
    case 'newest':
    case 'posts':
    case 'control':
    case 'saved':
    case 'reported':
    case 'search':
    case 'community':
        require __DIR__ . '/pages/feed.php';
        break;
    case 'queues':
        require __DIR__ . '/pages/queues.php';
        break;
    case 'post':
        require __DIR__ . '/pages/post.php';
        break;
    case 'overview':
        require __DIR__ . '/pages/overview.php';
        break;
    case 'communities':
        require __DIR__ . '/pages/communities.php';
        break;
    case 'create-community':
        require __DIR__ . '/pages/create-community.php';
        break;
    case 'create':
        require __DIR__ . '/pages/create.php';
        break;
    case 'modmail':
    case 'messages':
        require __DIR__ . '/pages/inbox.php';
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
    case 'comments':
        require __DIR__ . '/pages/comments.php';
        break;
    case 'about':
        require __DIR__ . '/pages/about.php';
        break;
    case 'faqs':
        require __DIR__ . '/pages/faqs.php';
        break;
    default:
        echo '<h2>Page not found</h2>';
endswitch;
