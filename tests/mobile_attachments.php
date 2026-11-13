<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';

function expectAttachment(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$attachmentValue = 'https://mobile.example/uploads/image.jpg, https://mobile.example/uploads/report%20file.pdf, javascript:alert(1)';
ob_start();
require dirname(__DIR__) . '/app/Views/partials/mobile-attachments.php';
$postAttachments = ob_get_clean();

expectAttachment(substr_count($postAttachments, 'class="mobile-attachment-link"') === 2, 'Multiple post attachments were not rendered.');
expectAttachment(str_contains($postAttachments, '<img src="https://mobile.example/uploads/image.jpg"'), 'Image attachment preview was not rendered.');
expectAttachment(str_contains($postAttachments, 'Open or download: report file.pdf'), 'Document attachment link is missing its filename.');
expectAttachment(!str_contains($postAttachments, 'javascript:'), 'Unsafe attachment URL was rendered.');

$attachmentValue = 'https://mobile.example/uploads/%3Cscript%3E.png';
ob_start();
require dirname(__DIR__) . '/app/Views/partials/mobile-attachments.php';
$commentAttachments = ob_get_clean();
expectAttachment(str_contains($commentAttachments, 'alt="&lt;script&gt;.png"'), 'Attachment filename was not escaped in the image preview.');
expectAttachment(str_contains($commentAttachments, 'Open image: &lt;script&gt;.png'), 'Single comment attachment link was not rendered safely.');

$mobileAvailable = true;
$csrf = '';
$mobilePost = [
    'ai_categories' => '',
    'ai_flagged' => false,
    'category' => 'College',
    'username' => 'test-user',
    'moderation_status' => 'pending',
    'title' => 'Post with files',
    'content' => 'Post body',
    'file_url' => 'https://mobile.example/uploads/post-image.png,https://mobile.example/uploads/notes.pdf',
    'report_count' => 0,
    'report_reason' => null,
    'is_archived' => false,
    'id' => 1,
    'comments' => [[
        'username' => 'commenter',
        'ai_flagged' => false,
        'content' => '',
        'report_count' => 0,
        'report_reasons' => null,
        'file_url' => 'https://mobile.example/uploads/comment-image.jpg',
        'id' => 2,
    ]],
];
ob_start();
require dirname(__DIR__) . '/app/Views/pages/mobile-post.php';
$postView = ob_get_clean();
expectAttachment(substr_count($postView, 'class="mobile-attachments"') === 2, 'The post and its comment attachments were not both rendered.');
expectAttachment(substr_count($postView, 'class="mobile-attachment-preview"') === 2, 'Image attachments were not previewed in the post details.');
expectAttachment(str_contains($postView, 'Open or download: notes.pdf'), 'The post document attachment is missing.');

$attachmentValue = '';
ob_start();
require dirname(__DIR__) . '/app/Views/partials/mobile-attachments.php';
expectAttachment(ob_get_clean() === '', 'An empty attachment list should not render a section.');

echo "Mobile attachment rendering tests passed.\n";
