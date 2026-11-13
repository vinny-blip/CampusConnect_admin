<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';

function expectMobileLoginPagination(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$activeUserCount = 0;
$loginSessions = [];
$sessionPage = 1;
$sessionPageCount = 2;
ob_start();
require dirname(__DIR__) . '/app/Views/pages/logs.php';
$firstPage = ob_get_clean();
expectMobileLoginPagination(
    str_contains($firstPage, 'href="logs.php?page=2">Next</a>'),
    'The first page should link to the next page when more than 25 sessions exist.'
);
expectMobileLoginPagination(
    str_contains($firstPage, 'Page 1 of 2'),
    'The pagination should show the current page and page count.'
);

$sessionPage = 2;
ob_start();
require dirname(__DIR__) . '/app/Views/pages/logs.php';
$lastPage = ob_get_clean();
expectMobileLoginPagination(
    str_contains($lastPage, 'href="logs.php?page=1">Previous</a>'),
    'A later page should link back to the previous page.'
);
expectMobileLoginPagination(
    !str_contains($lastPage, '>Next</a>'),
    'The last page should not offer a next-page link.'
);

$sessionPage = 1;
$sessionPageCount = 1;
ob_start();
require dirname(__DIR__) . '/app/Views/pages/logs.php';
$singlePage = ob_get_clean();
expectMobileLoginPagination(
    !str_contains($singlePage, 'login-log-pagination'),
    'Pagination controls should be hidden when 25 or fewer sessions fit on one page.'
);

echo "Mobile login log pagination view tests passed.\n";
