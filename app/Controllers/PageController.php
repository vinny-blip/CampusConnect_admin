<?php
declare(strict_types=1);

final class PageController
{
    private ?PDO $db = null;
    private ?UserModel $users = null;
    private ?array $user = null;
    private ?CommunityModel $communities = null;
    private ?PostModel $posts = null;
    private ?InboxModel $inbox = null;
    private ?NotificationModel $notifications = null;
    private ?RestrictedModel $restricted = null;
    private ?AiModerationModel $aiModeration = null;
    private string $page;
    private bool $databaseUnavailable = false;
    private bool $usingLocalStorage = false;

    public function __construct(string $page)
    {
        $this->page = $page;
        if ($page === 'index') {
            $this->redirect('login.php');
        }
        if ($page === 'dashboard') {
            $this->redirect('queues.php');
        }

        try {
            $this->db = Database::connection();
            $this->users = new UserModel($this->db);
            $this->usingLocalStorage = Database::usingLocalStorage();
        } catch (PDOException $exception) {
            if ($this->page === 'login') {
                $this->databaseUnavailable = true;
                return;
            }
            error_log($exception->getMessage());
            http_response_code(503);
            exit('<!doctype html><html lang="en"><meta charset="utf-8"><title>Database setup required</title>'
                . '<body><h1>CampusConnect needs MySQL</h1><p>Start MySQL in XAMPP and import '
                . '<code>config/database.sql</code> in phpMyAdmin, then reload this page.</p></body></html>');
        }
    }

    public function run(): void
    {
        if ($this->page === 'designatmobbin') {
            $this->page = 'community';
            $_GET['c'] = 'designatmobbin';
        }

        $this->user = $this->users !== null && isset($_SESSION['user_id'])
            ? $this->users->findById((int)$_SESSION['user_id'])
            : null;
        if ($this->users !== null && isset($_SESSION['offline_demo_login']) && $_SESSION['offline_demo_login'] === true) {
            $this->user = $this->users->findByEmail('mod@campus.edu.ph');
            if ($this->user !== null) {
                $_SESSION['user_id'] = (int)$this->user['id'];
                unset($_SESSION['offline_demo_login']);
            }
        } elseif ($this->databaseUnavailable && !empty($_SESSION['offline_demo_login'])) {
            $this->user = ['id' => 0, 'name' => 'Demo Moderator', 'email' => 'mod@campus.edu.ph'];
        }
        if ($this->users !== null && isset($_SESSION['user_id']) && $this->user === null) {
            unset($_SESSION['user_id']);
        }

        if (in_array($this->page, ['login', 'register'], true)) {
            if ($this->user !== null && !$this->databaseUnavailable) {
                $this->redirect('home.php');
            }
        } elseif ($this->user === null) {
            $this->redirect('login.php');
        } else {
            $this->initializeModels((int)$this->user['id']);
        }

        if ($this->page === 'restricted' && ($this->user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            exit('Only the admin can manage moderator restrictions.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        }
        if ($this->page === 'notifications' && isset($_GET['open'])) {
            $item = $this->notifications->markRead((int)$_GET['open']);
            if ($item !== null && preg_match('/^[a-z]+\.php(?:\?[a-zA-Z0-9_=&%-]*)?$/', $item['link'])) {
                $this->redirect($item['link']);
            }
            $this->redirect('notifications.php');
        }
        if (in_array($this->page, ['modmail', 'messages'], true) && isset($_GET['t'])) {
            $thread = $this->inbox->get((int)$_GET['t']);
            if ($thread !== null && $thread['box'] === ($this->page === 'modmail' ? 'mod' : 'dm')) {
                $this->inbox->markRead((int)$thread['id']);
            }
        }
        if ($this->page === 'post' && isset($_GET['id'])) {
            $reviewPost = $this->posts->get((int)$_GET['id']);
            if ($reviewPost !== null) {
                $this->posts->markReviewed((int)$reviewPost['id']);
            }
        }

        $data = $this->pageData();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        $csrf = $this->csrfField();
        (new ViewRenderer())->render(
            $this->page,
            $data,
            $flash,
            $csrf,
            $this->databaseUnavailable,
            $this->usingLocalStorage
        );
    }

    private function initializeModels(int $userId): void
    {
        $this->communities = new CommunityModel($this->db, $userId);
        $this->posts = new PostModel($this->db, $userId);
        $this->inbox = new InboxModel($this->db, $userId);
        $this->notifications = new NotificationModel($this->db, $userId);
        $this->restricted = new RestrictedModel($this->db);
        $this->aiModeration = new AiModerationModel($this->db);
    }

    private function handlePost(): void
    {
        $expectedToken = (string)($_SESSION['csrf_token'] ?? '');
        $submittedToken = (string)($_POST['csrf_token'] ?? '');
        if ($expectedToken === '' || $submittedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
            http_response_code(400);
            exit('The form expired. Go back, refresh the page, and try again.');
        }

        $action = (string)($_POST['action'] ?? '');
        if ($this->page === 'login' && $action === 'login') {
            $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            if ($this->users === null) {
                $user = hash_equals('mod@campus.edu.ph', $email) && hash_equals('mod12345', $password)
                    ? ['id' => 0, 'name' => 'Demo Moderator', 'email' => 'mod@campus.edu.ph']
                    : null;
            } else {
                $user = $this->users->login($email, $password);
            }
            if ($user === null) {
                $this->flash('Wrong email or password.', 'error');
                $this->redirect('login.php');
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            if ($this->databaseUnavailable) {
                $_SESSION['offline_demo_login'] = true;
                $this->flash('Demo login verified. Set up a database to use the dashboard.');
                $this->redirect('login.php');
            }
            $this->redirect('home.php');
        }
        if ($this->page === 'register' && $action === 'register') {
            $result = $this->users->register($_POST);
            if (isset($result['error'])) {
                $this->flash($result['error'], 'error');
                $this->redirect('register.php');
            }
            $this->flash('Account created. Log in to continue.', 'success');
            $this->redirect('login.php');
        }
        if ($action === 'logout') {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            $this->redirect('login.php');
        }
        if ($this->user === null) {
            $this->redirect('login.php');
        }

        $allowedActions = match ($this->page) {
            'home', 'newest', 'posts', 'control', 'saved', 'reported', 'search', 'community'
                => ['like', 'save', 'pin', 'restore', 'remove'],
            'post' => [
                'approve', 'remove', 'save', 'like-comment', 'heart-comment', 'comment',
                'clear-post-report', 'clear-comment-report',
                'delete-comment', 'hide-comment', 'restore-comment', 'delete-post', 'edit-post', 'toggle-lock',
            ],
            'queues' => [
                'approve', 'remove', 'toggle-ai-moderation', 'clear-post-report',
                'clear-comment-report', 'delete-comment', 'hide-comment', 'delete-post', 'approve-ai-comment',
            ],
            'create' => ['create-post'],
            'communities' => ['star', 'leave', 'Alumni', 'Advisor'],
            'create-community' => ['create-community'],
            'modmail', 'messages' => ['reply', 'new-message'],
            'notifications' => ['read-all'],
            'profile' => ['update-name', 'reset'],
            'restricted' => ['restrict', 'unrestrict'],
            'comments' => ['delete-comment', 'clear-comment-report', 'hide-comment', 'restore-comment'],
            default => [],
        };
        if (!in_array($action, $allowedActions, true)) {
            http_response_code(400);
            exit('Unsupported form action.');
        }

        $postId = (int)($_POST['post_id'] ?? ($this->page === 'post' ? ($_GET['id'] ?? 0) : 0));
        if (in_array($action, ['approve', 'remove', 'delete-post'], true)) {
            $post = $this->posts->get($postId);
            $this->requireAdminForAiReview(!empty($post['aiNeedsReview']));
        }
        if (in_array($action, ['approve-ai-comment', 'delete-comment', 'hide-comment', 'restore-comment'], true)) {
            $this->requireAdminForAiReview(
                $this->posts->commentNeedsAiReview($postId, (int)($_POST['comment_id'] ?? 0))
            );
        }

        if (in_array($action, ['create-post', 'comment'], true)
            && $this->restricted->isRestricted((int)$this->user['id'])) {
            $this->flash('Your account is restricted from posting and commenting.', 'error');
            $this->redirect($this->returnUrl());
        }

        switch ($this->page) {
            case 'home':
            case 'newest':
            case 'posts':
            case 'control':
            case 'saved':
            case 'reported':
            case 'search':
            case 'community':
                $this->handleFeedAction($action);
                break;
            case 'post':
                $this->handlePostAction($action);
                break;
            case 'queues':
                $this->handleQueueAction($action);
                break;
            case 'create':
                $this->handleCreatePost();
                break;
            case 'communities':
                $this->handleCommunityAction($action);
                break;
            case 'create-community':
                $result = $this->communities->create(
                    (string)($_POST['community_name'] ?? ''),
                    (string)($_POST['community_description'] ?? ''),
                    (string)($_POST['community_category'] ?? '')
                );
                if (isset($result['error'])) {
                    $this->flash($result['error'], 'error');
                    $this->redirect('create-community.php');
                }
                $this->flash('Community created.');
                $this->redirect('communities.php');
            case 'modmail':
            case 'messages':
                $this->handleInboxAction($action);
                break;
            case 'notifications':
                if ($action === 'read-all') {
                    $this->notifications->markAllRead();
                    $this->flash('All caught up.');
                }
                break;
            case 'profile':
                $this->handleProfileAction($action);
                break;
            case 'restricted':
                $this->handleRestrictedAction($action);
                break;
            case 'comments':
                $this->handleCommentAction($action);
                break;
        }
        $this->redirect($this->returnUrl());
    }

    private function handleFeedAction(string $action): void
    {
        $id = (int)($_POST['post_id'] ?? 0);
        if ($action === 'like') {
            $this->posts->toggleLike($id);
        } elseif ($action === 'save') {
            $this->flash($this->posts->toggleSave($id) ? 'Saved.' : 'Removed from saved.');
        } elseif ($action === 'pin') {
            $this->posts->togglePin($id);
        } elseif ($action === 'restore') {
            $this->posts->restore($id, $this->user['name']);
            $this->flash('Sent back to the queue.');
        } elseif ($action === 'remove') {
            $result = $this->posts->setStatus($id, 'removed', $this->user['name']);
            $this->flash($result['error'] ?? 'Post removed.', isset($result['error']) ? 'error' : 'success');
        }
    }

    private function requireAdminForAiReview(bool $needsReview): void
    {
        if ($needsReview && ($this->user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            exit('Only the admin can decide AI-flagged content.');
        }
    }

    private function handleQueueAction(string $action): void
    {
        $postId = (int)($_POST['post_id'] ?? 0);
        $commentId = (int)($_POST['comment_id'] ?? 0);
        if (in_array($action, ['approve', 'remove', 'delete-post'], true)) {
            $post = $this->posts->get($postId);
            $this->requireAdminForAiReview(!empty($post['aiNeedsReview']));
        }
        if (in_array($action, ['approve-ai-comment', 'delete-comment', 'hide-comment'], true)) {
            $this->requireAdminForAiReview($this->posts->commentNeedsAiReview($postId, $commentId));
        }
        if ($action === 'approve-ai-comment') {
            $approved = $this->posts->approveAiComment($postId, $commentId);
            $this->flash($approved ? 'AI-flagged comment approved.' : 'Comment is no longer awaiting AI review.', $approved ? 'success' : 'error');
            return;
        }
        if ($action === 'delete-post') {
            $deleted = $this->posts->deletePost($postId, $this->user['name']);
            $this->flash($deleted ? 'Post permanently deleted.' : 'Post is no longer reported or does not exist.', $deleted ? 'success' : 'error');
            return;
        }
        if ($action === 'clear-post-report') {
            $cleared = $this->posts->clearPostReport((int)($_POST['post_id'] ?? 0));
            $this->flash($cleared ? 'Post report dismissed.' : 'Post report was already cleared.', $cleared ? 'success' : 'error');
            return;
        }
        if (in_array($action, ['clear-comment-report', 'delete-comment', 'hide-comment'], true)) {
            if ($action === 'delete-comment') {
                $this->posts->removeComment($postId, $commentId);
                $this->flash('Comment removed.');
            } elseif ($action === 'hide-comment') {
                $hidden = $this->posts->hideComment($postId, $commentId);
                $this->flash($hidden ? 'Comment hidden and report closed.' : 'Comment not found.', $hidden ? 'success' : 'error');
            } else {
                $cleared = $this->posts->clearCommentReport($postId, $commentId);
                $this->flash($cleared ? 'Comment report dismissed.' : 'Comment report was already cleared.', $cleared ? 'success' : 'error');
            }
            return;
        }
        $status = $action === 'approve' ? 'approved' : ($action === 'remove' ? 'removed' : '');
        if ($status === '') {
            if ($action === 'toggle-ai-moderation') {
                $enabled = ($_POST['enabled'] ?? '') === '1';
                if ($enabled && !$this->aiModeration->configured()) {
                    $this->flash('Configure OPENAI_API_KEY on the server before enabling AI moderation.', 'error');
                    return;
                }
                $this->aiModeration->setEnabled($enabled);
                $this->flash($enabled ? 'AI moderation preference turned on.' : 'AI moderation preference turned off.');
            }
            return;
        }
        $result = $this->posts->setStatus((int)($_POST['post_id'] ?? 0), $status, $this->user['name']);
        $this->flash($result['error'] ?? ($status === 'approved' ? 'Post approved.' : 'Post removed.'), isset($result['error']) ? 'error' : 'success');
    }

    private function handlePostAction(string $action): void
    {
        $id = (int)($_GET['id'] ?? $_POST['post_id'] ?? 0);
        $commentId = (int)($_POST['comment_id'] ?? 0);
        if (in_array($action, ['approve', 'remove', 'delete-post'], true)) {
            $post = $this->posts->get($id);
            $this->requireAdminForAiReview(!empty($post['aiNeedsReview']));
        }
        if (in_array($action, ['delete-comment', 'hide-comment', 'restore-comment'], true)) {
            $this->requireAdminForAiReview($this->posts->commentNeedsAiReview($id, $commentId));
        }
        if ($action === 'approve' || $action === 'remove') {
            $result = $this->posts->setStatus($id, $action === 'approve' ? 'approved' : 'removed', $this->user['name']);
            $this->flash($result['error'] ?? ($action === 'approve' ? 'Post approved.' : 'Post removed.'), isset($result['error']) ? 'error' : 'success');
            if (!isset($result['error']) && isset($_GET['review'])) {
                $this->redirect('queues.php');
            }
        } elseif ($action === 'save') {
            $this->flash($this->posts->toggleSave($id) ? 'Saved.' : 'Removed from saved.');
        } elseif ($action === 'delete-post') {
            $deleted = $this->posts->deletePost($id, $this->user['name']);
            $this->flash($deleted ? 'Post permanently deleted.' : 'Post is no longer reported or does not exist.', $deleted ? 'success' : 'error');
            if ($deleted) {
                $this->redirect(isset($_GET['review']) ? 'queues.php?tab=reported' : 'home.php');
            }
        } elseif ($action === 'clear-post-report') {
            $cleared = $this->posts->clearPostReport($id);
            $this->flash($cleared ? 'Post report dismissed.' : 'Post report was already cleared.', $cleared ? 'success' : 'error');
        } elseif ($action === 'clear-comment-report') {
            $cleared = $this->posts->clearCommentReport($id, $commentId);
            $this->flash($cleared ? 'Comment report dismissed.' : 'Comment report was already cleared.', $cleared ? 'success' : 'error');
        } elseif ($action === 'delete-comment') {
            $this->posts->removeComment($id, $commentId);
            $this->flash('Comment removed.');
        } elseif ($action === 'hide-comment' || $action === 'restore-comment') {
            $hidden = $action === 'hide-comment';
            $updated = !$hidden && $this->posts->commentNeedsAiReview($id, $commentId)
                ? $this->posts->approveAiComment($id, $commentId)
                : $this->posts->hideComment($id, $commentId, $hidden);
            $this->flash(!$updated ? 'Comment not found.' : ($hidden ? 'Comment hidden.' : 'Comment restored.'), $updated ? 'success' : 'error');
        } elseif ($action === 'edit-post') {
            $result = $this->posts->updatePost(
                $id,
                (string)($_POST['title'] ?? ''),
                (string)($_POST['body'] ?? ''),
                (string)($_POST['tags'] ?? '')
            );
            $this->flash($result['error'] ?? 'Post updated.', isset($result['error']) ? 'error' : 'success');
        } elseif ($action === 'toggle-lock') {
            $locked = $this->posts->toggleLock($id);
            $this->flash($locked === null ? 'Post not found.' : ($locked ? 'Comments locked.' : 'Comments unlocked.'), $locked === null ? 'error' : 'success');
        } elseif ($action === 'like-comment' || $action === 'heart-comment') {
            $this->posts->reactComment($id, $commentId, $action === 'like-comment' ? 'likes' : 'hearts');
        } elseif ($action === 'comment') {
            $commentText = (string)($_POST['text'] ?? '');
            $aiReview = $this->moderateSubmission($commentText);
            if (!$this->posts->addComment($id, $this->user['name'], $commentText, $aiReview)) {
                $post = $this->posts->get($id);
                $message = $post === null
                    ? 'Post not found.'
                    : (!empty($post['locked']) ? 'Comments are locked for this post.' : ($post['status'] !== 'approved' ? 'Comments are unavailable for this post.' : 'Write a comment first.'));
                $this->flash($message, 'error');
            } elseif (!empty($aiReview['needsReview'])) {
                $this->flash(!empty($aiReview['available'])
                    ? 'Comment held for admin review after an AI flag.'
                    : 'AI check unavailable. Comment held for admin review.');
            } elseif (isset($_GET['reply'])) {
                $this->redirect('post.php?id=' . $id . (isset($_GET['review']) ? '&review=1' : ''));
            }
        }
    }

    private function handleCommentAction(string $action): void
    {
        $postId = (int)($_POST['post_id'] ?? 0);
        $commentId = (int)($_POST['comment_id'] ?? 0);
        if (in_array($action, ['delete-comment', 'hide-comment', 'restore-comment'], true)) {
            $this->requireAdminForAiReview($this->posts->commentNeedsAiReview($postId, $commentId));
        }
        if ($action === 'delete-comment') {
            $this->posts->removeComment($postId, $commentId);
            $this->flash('Comment removed.');
        } elseif ($action === 'hide-comment' || $action === 'restore-comment') {
            $hidden = $action === 'hide-comment';
            $updated = !$hidden && $this->posts->commentNeedsAiReview($postId, $commentId)
                ? $this->posts->approveAiComment($postId, $commentId)
                : $this->posts->hideComment($postId, $commentId, $hidden);
            $this->flash(!$updated ? 'Comment not found.' : ($hidden ? 'Comment hidden.' : 'Comment restored.'), $updated ? 'success' : 'error');
        } elseif ($action === 'clear-comment-report') {
            $cleared = $this->posts->clearCommentReport($postId, $commentId);
            $this->flash($cleared ? 'Comment report dismissed.' : 'Comment report was already cleared.', $cleared ? 'success' : 'error');
        }
    }

    private function handleCreatePost(): void
    {
        $image = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['image'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->flash('The image could not be uploaded. Check your server upload limits and try again.', 'error');
                return;
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $allowed = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime]) || getimagesize($file['tmp_name']) === false) {
                $this->flash('Choose a valid JPG, PNG, GIF, or WebP image.', 'error');
                return;
            }
            $contents = file_get_contents($file['tmp_name']);
            if ($contents === false) {
                $this->flash('The image could not be read. Please try uploading it again.', 'error');
                return;
            }
            $image = 'data:' . $mime . ';base64,' . base64_encode($contents);
        }
        $postData = $_POST;
        $postData['image'] = $image;
        $communitySlugs = array_column($this->communities->list(), 'slug');
        $aiReview = $this->moderateSubmission(
            trim((string)($postData['title'] ?? '') . "\n" . (string)($postData['body'] ?? '')),
            $image
        );
        $result = $this->posts->add($postData, $this->user['name'], $communitySlugs, $aiReview);
        if (isset($result['error'])) {
            $this->flash($result['error'], 'error');
        } elseif (!empty($aiReview['needsReview'])) {
            $this->flash(!empty($aiReview['available'])
                ? 'Post held for admin review after an AI flag.'
                : 'AI check unavailable. Post held for admin review.');
            $this->redirect('queues.php');
        } else {
            $this->flash('Post published.');
            $this->redirect('post.php?id=' . $result['id']);
        }
    }

    private function moderateSubmission(string $text, string $image = ''): array
    {
        if (!$this->aiModeration->enabled()) {
            return ['available' => true, 'flagged' => false, 'categories' => [], 'needsReview' => false];
        }

        $result = $this->aiModeration->inspect($text, $image);
        $result['needsReview'] = empty($result['available']) || !empty($result['flagged']);
        return $result;
    }

    private function handleCommunityAction(string $action): void
    {
        $slug = (string)($_POST['slug'] ?? '');
        $name = $this->communities->name($slug);
        if ($action === 'star' || $action === 'leave' || in_array($action, ['Alumni', 'Advisor'], true)) {
            $this->communities->change($slug, $action);
        }
        if (in_array($action, ['Alumni', 'Advisor'], true)) {
            $this->notifications->add("{$action} role requested for {$name}.", 'communities.php');
            $this->flash("{$action} role requested for {$name}.");
        } elseif ($action === 'leave') {
            $this->flash("You left {$name}.");
        }
    }

    private function handleInboxAction(string $action): void
    {
        $box = $this->page === 'modmail' ? 'mod' : 'dm';
        if ($action === 'reply') {
            $this->inbox->reply((int)($_POST['thread_id'] ?? 0), (string)($_POST['text'] ?? ''));
        } elseif ($action === 'new-message') {
            $result = $this->inbox->create(
                $box,
                (string)($_POST['who'] ?? ''),
                (string)($_POST['subject'] ?? ''),
                (string)($_POST['text'] ?? '')
            );
            if (isset($result['error'])) {
                $this->flash($result['error'], 'error');
            } else {
                $this->inbox->markRead((int)$result['id']);
                $this->flash('Message sent.');
                $this->redirect($this->page . '.php?t=' . $result['id']);
            }
        }
    }

    private function handleProfileAction(string $action): void
    {
        if ($action === 'update-name') {
            if (!$this->users->updateName((int)$this->user['id'], (string)($_POST['name'] ?? ''))) {
                $this->flash('Name cannot be empty.', 'error');
            } else {
                $this->user = $this->users->findById((int)$this->user['id']);
                $this->flash('Profile updated.');
            }
        } elseif ($action === 'reset') {
            $statement = $this->db->prepare('DELETE FROM admin_app_state WHERE user_id = ?');
            $statement->execute([(int)$this->user['id']]);
            $this->flash('Demo data reset. Your account was kept.');
        }
    }

    private function handleRestrictedAction(string $action): void
    {
        if ($action === 'restrict') {
            $error = $this->restricted->add(
                (string)($_POST['email'] ?? ''),
                (string)($_POST['reason'] ?? ''),
                (int)$this->user['id']
            );
            $this->flash($error ?? 'Moderator restricted.', $error === null ? 'success' : 'error');
        } elseif ($action === 'unrestrict') {
            $this->restricted->remove((int)($_POST['user_id'] ?? 0));
            $this->flash('Moderator can post and comment again.');
        }
    }

    private function pageData(): array
    {
        if (in_array($this->page, ['login', 'register'], true)) {
            return [];
        }
        $data = [
            'user' => $this->user,
            'noteCount' => $this->notifications->unread(),
            'messageCount' => $this->inbox->unread('dm'),
            'communities' => $this->communities->list(),
        ];
        if (in_array($this->page, ['home', 'newest', 'posts', 'control', 'saved', 'reported', 'search', 'community'], true)) {
            $data += $this->feedData();
        } elseif ($this->page === 'queues') {
            $tab = (string)($_GET['tab'] ?? '');
            $kind = in_array($tab, ['reported', 'ai'], true) ? $tab : 'approve';
            $query = trim((string)($_GET['q'] ?? ''));
            $data += [
                'kind' => $kind,
                'query' => $query,
                'counts' => $this->posts->counts(),
                'posts' => $this->named($this->posts->queue($kind, $query)),
                'reportedComments' => $kind === 'reported' ? $this->posts->reportedComments($query) : [],
                'aiReviewComments' => $kind === 'ai' ? $this->posts->aiReviewComments($query) : [],
                'aiModerationEnabled' => $this->aiModeration->enabled(),
                'aiModerationConfigured' => $this->aiModeration->configured(),
            ];
        } elseif ($this->page === 'post') {
            $id = (int)($_GET['id'] ?? 0);
            $data['post'] = $this->posts->get($id);
            if ($data['post'] !== null) {
                $data['post']['cname'] = $this->communities->name((string)$data['post']['community']);
            }
            $data['back'] = isset($_GET['review']) ? 'queues.php' : $this->safeReferrer();
            $data['replyTo'] = trim((string)($_GET['reply'] ?? ''));
        } elseif ($this->page === 'overview') {
            $data['counts'] = $this->posts->counts();
            $data['activity'] = $this->posts->activity();
            $data['reportCountsByDate'] = $this->posts->reportCountsByDate();
        } elseif ($this->page === 'communities') {
            $data['communityCount'] = count($data['communities']);
        } elseif ($this->page === 'create-community') {
            $data['communityCount'] = count($data['communities']);
        } elseif (in_array($this->page, ['modmail', 'messages'], true)) {
            $box = $this->page === 'modmail' ? 'mod' : 'dm';
            $currentId = (int)($_GET['t'] ?? 0);
            $current = $currentId ? $this->inbox->get($currentId) : null;
            if ($current !== null && $current['box'] !== $box) {
                $current = null;
            }
            $data['box'] = $box;
            $data['threads'] = $this->inbox->threads($box);
            $data['current'] = $current;
        } elseif ($this->page === 'notifications') {
            $data['notifications'] = $this->notifications->list();
        } elseif ($this->page === 'profile') {
            $data['counts'] = $this->posts->counts();
            $data['savedCount'] = count($this->posts->saved());
        } elseif ($this->page === 'restricted') {
            $data['restrictedUsers'] = $this->restricted->list();
        } elseif ($this->page === 'comments') {
            $data['comments'] = $this->posts->comments();
        }
        return $data;
    }

    private function feedData(): array
    {
        $status = (string)($_GET['status'] ?? '');
        if (!in_array($status, ['pending', 'approved', 'removed'], true)) {
            $status = '';
        }
        $query = trim((string)($_GET['q'] ?? ''));
        $slug = trim((string)($_GET['c'] ?? 'designatmobbin'));
        $config = [
            'home' => ['Home', 'Approved posts from the communities you moderate.', 'home'],
            'newest' => ['Newest', 'The most recently approved posts first.', 'newest'],
            'posts' => ['Posts', 'Every post across your communities. Filter by status.', 'posts'],
            'control' => ['Control Posts/Feed', 'Pin or remove posts in the feed. Pending posts have to be checked in the queue first.', 'control'],
            'saved' => ['Saved', 'Posts you saved for later.', 'saved'],
            'reported' => ['Reported', 'Posts that members reported. Review pending ones in the reported queue.', 'reported'],
        ];
        $title = $config[$this->page][0] ?? 'Search';
        $sub = $config[$this->page][1] ?? '';
        $posts = [];
        $about = null;
        $searchCommunities = [];

        if ($this->page === 'home') {
            $posts = $this->posts->find('approved', null, '', null, 'hot');
        } elseif ($this->page === 'newest') {
            $posts = $this->posts->find('approved', null, '', null, 'approved');
        } elseif ($this->page === 'posts' || $this->page === 'control') {
            $posts = $this->posts->find($status ?: null);
        } elseif ($this->page === 'saved') {
            $posts = $this->posts->saved();
        } elseif ($this->page === 'reported') {
            $posts = $this->posts->find(null, null, '', true);
        } elseif ($this->page === 'community') {
            $about = $this->communities->get($slug);
            $title = $about['name'] ?? 'Community not found';
            $sub = $about === null ? 'See your <a href="communities.php">communities</a>.' : '';
            $posts = $about === null ? [] : $this->posts->find('approved', $slug, '', null, 'hot');
        } elseif ($this->page === 'search') {
            $posts = $this->posts->find(null, null, $query);
            $title = $query !== '' ? 'Results for “' . $query . '”' : 'Search';
            $sub = $query !== '' ? count($posts) . ' posts found.' : 'Type something in the search bar above.';
            $searchCommunities = $this->communities->search($query);
        }
        return [
            'feedTitle' => $title,
            'feedSub' => $sub,
            'filter' => $status ?: 'all',
            'feedPosts' => $this->named($posts),
            'about' => $about,
            'query' => $query,
            'searchCommunities' => $searchCommunities,
            'mode' => $this->page,
            'topLikedToday' => $this->page === 'home' ? $this->posts->topLikedToday() : [],
            'reportCountsByDate' => $this->page === 'home' ? $this->posts->reportCountsByDate(7) : [],
        ];
    }

    private function named(array $posts): array
    {
        foreach ($posts as &$post) {
            $post['cname'] = $this->communities->name((string)$post['community']);
        }
        unset($post);
        return $posts;
    }

    private function csrfField(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }

    private function returnUrl(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($uri !== '' && str_starts_with($uri, '/') && !str_starts_with($uri, '//') && !str_contains($uri, "\r") && !str_contains($uri, "\n")) {
            return $uri;
        }
        return $this->page . '.php';
    }

    private function safeReferrer(): string
    {
        $referrer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $parts = parse_url($referrer);
        $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $host = strtolower((string)parse_url('http://' . $requestHost, PHP_URL_HOST));
        $referrerHost = strtolower((string)($parts['host'] ?? $host));
        $filename = isset($parts['path']) ? basename($parts['path']) : '';
        $query = (string)($parts['query'] ?? '');
        if ($host !== '' && $referrerHost === $host && preg_match('/^[a-z]+\.php$/', $filename)
            && preg_match('/^[a-zA-Z0-9_=&%-]*$/', $query)) {
            return $filename . ($query === '' ? '' : '?' . $query);
        }
        return 'posts.php';
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
