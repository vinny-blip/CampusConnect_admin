<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Router.php';

$normalize = new ReflectionMethod(Router::class, 'normalize');
if ($normalize->invoke(null, 'restricted.php') !== 'not-found') {
    throw new RuntimeException('The old restrictions URL must no longer resolve to a page.');
}
if ($normalize->invoke(null, 'user-accounts.php') !== 'user-accounts') {
    throw new RuntimeException('The user accounts page must remain accessible.');
}

echo "Restrictions page removal tests passed.\n";
