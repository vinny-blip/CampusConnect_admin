<?php
declare(strict_types=1);

final class PageController
{
    private ?PDO $db = null;
    private ?UserModel $users = null;
    private ?MobileLoginLogModel $mobileLoginLogs = null;
    private ?array $user = null;
    private ?PostModel $posts = null;
    private ?NotificationModel $notifications = null;
    private ?RestrictedModel $restricted = null;
    private ?AiModerationModel $aiModeration = null;
    private ?MobileModerationModel $mobile = null;
    private string $page;

    /** Prepares the database and records which dashboard page was requested. */
    public function __construct(string $page)
    {
        $this->page = $page;
        if ($page === 'index') {
            $this->redirect('login.php');
        }
        if ($page === 'dashboard') {
            $this->redirect('control.php');
        }
        if ($page === 'queues') {
            $tab = (string)($_GET['tab'] ?? '');
            $this->redirect(match ($tab) {
                'reported' => 'reported.php',
                'ai' => 'comments.php',
                default => 'control.php?status=pending',
            });
        }

        try {
            $this->db = Database::connection();
            $this->users = new UserModel($this->db);
        } catch (RuntimeException $exception) {
            error_log($exception->getMessage());
            http_response_code(503);
            $message = h($exception->getMessage());
            exit('<!doctype html><html lang="en"><meta charset="utf-8"><title>Database setup required</title>'
                . '<body><h1>CampusConnect needs MySQL</h1><p>' . $message . '</p>'
                . '<p>Verify the Hostinger database settings and imported schema, then reload this page.</p></body></html>');
        }
    }

    /** Authenticates the request, handles actions, and renders the page. */
    public function run(): void
    {
        $this->user = $this->users !== null && isset($_SESSION['user_id'])
            ? $this->users->findById((int)$_SESSION['user_id'])
            : null;
        if ($this->users !== null && isset($_SESSION['user_id']) && $this->user === null) {
            unset($_SESSION['user_id']);
        }

        if (in_array($this->page, ['login', 'register'], true)) {
            if ($this->user !== null) {
                $this->redirect('home.php');
            }
        } elseif ($this->user === null) {
            $this->redirect('login.php');
        } else {
            $this->initializeModels((int)$this->user['id']);
            if ($this->page === 'overview') {
                $this->redirect('control.php');
            }
        }

        if ($this->page === 'restricted' && ($this->user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            exit('Only the admin can manage moderator restrictions.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        }
        if ($this->page === 'logs' && isset($_GET['poll'])) {
            // Send refreshed counts and session rows to the Logs page.
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'active_users' => $this->mobileLoginLogs()->activeUserCount(),
                'sessions' => $this->mobileLoginLogs()->recentSessions(),
            ], JSON_THROW_ON_ERROR);
            exit;
        }
        if ($this->page === 'users' && isset($_GET['poll'])) {
            // Expire due suspensions and return minimal status data for the live Users list.
            $timezoneOffset = filter_var($_GET['timezone_offset'] ?? null, FILTER_VALIDATE_INT);
            $timezoneOffset = $timezoneOffset !== false && abs($timezoneOffset) <= 840 ? $timezoneOffset : null;
            $restored = $this->mobile->restoreExpiredUserSuspensions($timezoneOffset);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'restored' => $restored,
                'users' => array_map(
                    static fn(array $mobileUser): array => [
                        'id' => (int)$mobileUser['id'],
                        'moderation_status' => (string)$mobileUser['moderation_status'],
                    ],
                    $this->mobile->userStatuses()
                ),
            ], JSON_THROW_ON_ERROR);
            exit;
        }
        $this->syncAiFlaggedPostNotifications();
        if ($this->page === 'notifications' && isset($_GET['poll'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['unread' => $this->notifications->unread()], JSON_THROW_ON_ERROR);
            exit;
        }
        if ($this->page === 'notifications' && isset($_GET['open'])) {
            $item = $this->notifications->markRead((int)$_GET['open']);
            if ($item !== null && preg_match('/^[a-z-]+\.php(?:\?[a-zA-Z0-9_=&%-]*)?$/', $item['link'])) {
                $this->redirect($item['link']);
            }
            $this->redirect('notifications.php');
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
            $csrf
        );
    }

    /** Creates the data models used by authenticated dashboard pages. */
    private function initializeModels(int $userId): void
    {
        $this->posts = new PostModel($this->db, $userId);
        $this->notifications = new NotificationModel($this->db, $userId);
        $this->restricted = new RestrictedModel($this->db);
        $this->aiModeration = new AiModerationModel($this->db);
        $this->mobile = new MobileModerationModel($this->db, $userId);
    }

    /** Creates the login-log model only when the Logs page needs it. */
    /** Creates the login-log model only when the Logs page needs it. */
    private function mobileLoginLogs(): MobileLoginLogModel
    {
        if ($this->mobileLoginLogs === null) {
            $this->mobileLoginLogs = new MobileLoginLogModel($this->db);
        }
        return $this->mobileLoginLogs;
    }

    /** Adds notifications for newly AI-flagged mobile posts and comments. */
    private function syncAiFlaggedPostNotifications(): void
    {
        if ($this->mobile === null || $this->notifications === null || !$this->mobile->available()) {
            return;
        }
        $notifications = [];
        foreach ($this->mobile->recentlyAiFlaggedPosts() as $post) {
            $postId = (int)$post['id'];
            $title = trim((string)$post['title']);
            $name = trim((string)$post['full_name']);
            $college = trim((string)($post['college'] ?? ''));
            $notifications[] = [
                'text' => 'AI flagged a post by ' . ($name !== '' ? $name : 'Unknown user')
                    . ' from ' . ($college !== '' ? $college : 'an unspecified college') . ': '
                    . ($title !== '' ? $title : 'Untitled post'),
                'link' => 'mobile-post.php?id=' . $postId . '&notification=ai-flagged',
            ];
        }
        foreach ($this->mobile->recentlyAiFlaggedComments() as $comment) {
            $snippet = trim((string)$comment['content']);
            if (mb_strlen($snippet) > 60) {
                $snippet = mb_substr($snippet, 0, 57) . '...';
            }
            $name = trim((string)$comment['full_name']);
            $college = trim((string)($comment['college'] ?? ''));
            $notifications[] = [
                'text' => 'AI flagged a comment by ' . ($name !== '' ? $name : 'Unknown user')
                    . ' from ' . ($college !== '' ? $college : 'an unspecified college') . ': '
                    . ($snippet !== '' ? $snippet : 'Empty comment'),
                'link' => 'mobile-post.php?id=' . (int)$comment['post_id'] . '&comment=' . (int)$comment['id'] . '&notification=ai-flagged',
            ];
        }
        $this->notifications->addManyOnce($notifications);
    }

    /** Validates the request token and dispatches the submitted form action. */
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
            $user = $this->users->login($email, $password);
            if ($user === null) {
                $this->flash('Wrong email or password.', 'error');
                $this->redirect('login.php');
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
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
            'mobile-feed', 'mobile-queues', 'mobile-reports', 'mobile-post', 'mobile-comments',
            'home', 'posts', 'control', 'reported', 'comments', 'removed', 'users'
                => ['mobile-approve', 'mobile-remove', 'mobile-restore-post', 'mobile-restore-comment-archive', 'mobile-delete-comment', 'mobile-hide-comment', 'mobile-restore-comment', 'mobile-suspend-user', 'mobile-ban-user', 'mobile-restore-user', 'mobile-dismiss-report', 'mobile-restore-report', 'toggle-ai-moderation'],
            'home', 'posts', 'control', 'reported', 'search'
                => ['like', 'pin', 'restore', 'remove', 'mobile-dismiss-report'],
            'post' => [
                'approve', 'remove', 'like-comment', 'heart-comment', 'comment',
                'clear-post-report', 'clear-comment-report',
                'delete-comment', 'hide-comment', 'restore-comment', 'delete-post', 'edit-post', 'toggle-lock',
            ],
            'notifications' => ['read-all'],
            'profile' => ['update-name', 'update-profile-picture', 'change-password'],
            'restricted' => ['restrict', 'unrestrict'],
            'comments' => ['delete-comment', 'clear-comment-report', 'hide-comment', 'restore-comment'],
            default => [],
        };
        if (!in_array($action, $allowedActions, true)) {
            http_response_code(400);
            exit('Unsupported form action.');
        }
        if (str_starts_with($this->page, 'mobile-') || in_array($this->page, ['home', 'posts', 'control', 'reported', 'comments', 'removed', 'users'], true)) {
            if ($action === 'toggle-ai-moderation') {
                $this->aiModeration->setEnabled((string)($_POST['enabled'] ?? '0') === '1');
                $this->flash('AI Moderation setting updated.');
            } elseif ($action === 'mobile-remove') {
                $removed = $this->mobile->removePost((int)($_POST['post_id'] ?? 0));
                $this->flash($removed ? 'Mobile post removed and the user was notified.' : 'Mobile post was not found.', $removed ? 'success' : 'error');
            } elseif ($action === 'mobile-restore-comment-archive') {
                $restored = $this->mobile->restoreComment((int)($_POST['archive_id'] ?? 0));
                $this->flash($restored ? 'Comment restored to the mobile app.' : 'This comment could not be restored (its post may no longer exist).', $restored ? 'success' : 'error');
            } elseif ($action === 'mobile-restore-post') {
                $restored = $this->mobile->restorePost((int)($_POST['archive_id'] ?? 0));
                $this->flash($restored ? 'Post restored to the mobile app.' : 'This post could not be restored.', $restored ? 'success' : 'error');
            } elseif ($action === 'mobile-approve') {
                $this->mobile->setPostStatus((int)($_POST['post_id'] ?? 0), 'approved', (string)($_POST['reason'] ?? ''));
                $this->flash('Mobile post status updated.');
            } elseif ($action === 'mobile-delete-comment') {
                $deleted = $this->mobile->removeComment((int)($_POST['comment_id'] ?? 0));
                $this->flash($deleted ? 'Mobile comment deleted and the user was notified.' : 'Mobile comment was not found.', $deleted ? 'success' : 'error');
            } elseif ($action === 'mobile-hide-comment' || $action === 'mobile-restore-comment') {
                $status = $action === 'mobile-hide-comment' ? 'hidden' : 'approved';
                $this->mobile->setCommentStatus((int)($_POST['comment_id'] ?? 0), $status);
                $this->flash('Mobile comment status updated.');
            } elseif (in_array($action, ['mobile-suspend-user', 'mobile-ban-user', 'mobile-restore-user'], true)) {
                $status = ['mobile-suspend-user' => 'suspended', 'mobile-ban-user' => 'banned', 'mobile-restore-user' => 'active'][$action];
                $suspendedUntil = null;
                $suspensionTimezoneOffset = null;
                if ($status === 'suspended') {
                    $submittedDateTime = (string)($_POST['suspended_until'] ?? '');
                    $suspensionTimezoneOffset = filter_var(
                        $_POST['suspension_timezone_offset'] ?? null,
                        FILTER_VALIDATE_INT
                    );
                    $suspensionTimezoneOffset = $suspensionTimezoneOffset === false ? null : $suspensionTimezoneOffset;
                    if ($suspensionTimezoneOffset === null || abs($suspensionTimezoneOffset) > 840) {
                        $this->flash('The browser time zone could not be verified. Refresh the page and try again.', 'error');
                        $this->redirect($this->returnUrl());
                    }
                    $offsetFromUtc = -$suspensionTimezoneOffset;
                    $offset = sprintf(
                        '%s%02d:%02d',
                        $offsetFromUtc >= 0 ? '+' : '-',
                        intdiv(abs($offsetFromUtc), 60),
                        abs($offsetFromUtc) % 60
                    );
                    $parsedDateTime = DateTimeImmutable::createFromFormat(
                        '!Y-m-d\TH:iP',
                        $submittedDateTime . $offset
                    );
                    if ($parsedDateTime === false
                        || $parsedDateTime->format('Y-m-d\TH:i') !== $submittedDateTime
                        || $parsedDateTime <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
                        $this->flash('Choose a suspension lift date and time in the future.', 'error');
                        $this->redirect($this->returnUrl());
                    }
                    $suspendedUntil = $parsedDateTime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                }
                $updated = $this->mobile->setUserStatus(
                    (int)($_POST['user_id'] ?? 0),
                    $status,
                    (string)($_POST['reason'] ?? ''),
                    $suspendedUntil,
                    $suspensionTimezoneOffset
                );
                $this->flash(
                    $updated ? ($status === 'suspended' ? 'User suspension lift time was saved.' : 'Mobile user status updated.') : 'Mobile user status could not be updated.',
                    $updated ? 'success' : 'error'
                );
            } elseif ($action === 'mobile-dismiss-report') {
                $updated = $this->mobile->setReportStatus((int)($_POST['report_id'] ?? 0), 'Dismissed');
                $this->flash($updated ? 'Mobile report dismissed.' : 'Mobile report was already updated.', $updated ? 'success' : 'error');
            } elseif ($action === 'mobile-restore-report') {
                $updated = $this->mobile->setReportStatus((int)($_POST['report_id'] ?? 0), 'Pending');
                $this->flash($updated ? 'Report restored to current reports.' : 'Mobile report was already updated.', $updated ? 'success' : 'error');
                $this->redirect($updated
                    ? ($this->page === 'mobile-reports' ? 'mobile-reports.php?view=current' : 'reported.php?view=current')
                    : $this->returnUrl());
            }
            $this->redirect($this->returnUrl());
        }
        if ($action === 'mobile-dismiss-report') {
            $updated = $this->mobile->setReportStatus((int)($_POST['report_id'] ?? 0), 'Dismissed');
            $this->flash($updated ? 'Mobile report dismissed.' : 'Mobile report was already updated.', $updated ? 'success' : 'error');
            $this->redirect($this->returnUrl());
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

        if ($action === 'comment'
            && $this->restricted->isRestricted((int)$this->user['id'])) {
            $this->flash('Your account is restricted from posting and commenting.', 'error');
            $this->redirect($this->returnUrl());
        }

        switch ($this->page) {
            case 'home':
            case 'posts':
            case 'control':
            case 'reported':
            case 'search':
                $this->handleFeedAction($action);
                break;
            case 'post':
                $this->handlePostAction($action);
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

    /** Handles feed-related actions such as publishing or searching posts. */
    private function handleFeedAction(string $action): void
    {
        $id = (int)($_POST['post_id'] ?? 0);
        if ($action === 'like') {
            $this->posts->toggleLike($id);
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

    /** Prevents non-admin users from changing the AI moderation review setting. */
    private function requireAdminForAiReview(bool $needsReview): void
    {
        if ($needsReview && ($this->user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            exit('Only the admin can decide AI-flagged content.');
        }
    }

    /** Performs the selected moderation action on a post. */
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
                $this->redirect('control.php?status=pending');
            }
        } elseif ($action === 'delete-post') {
            $deleted = $this->posts->deletePost($id, $this->user['name']);
            $this->flash($deleted ? 'Post permanently deleted.' : 'Post is no longer reported or does not exist.', $deleted ? 'success' : 'error');
            if ($deleted) {
                $this->redirect(isset($_GET['review']) ? 'reported.php' : 'home.php');
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

    /** Performs the selected moderation action on a comment. */
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

    /** Checks submitted text and an optional image with AI moderation. */
    private function moderateSubmission(string $text, string $image = ''): array
    {
        if (!$this->aiModeration->enabled()) {
            return ['available' => true, 'flagged' => false, 'categories' => [], 'needsReview' => false];
        }

        $result = $this->aiModeration->inspect($text, $image);
        $result['needsReview'] = empty($result['available']) || !empty($result['flagged']);
        return $result;
    }

    /** Handles account profile, password, and profile-image changes. */
    private function handleProfileAction(string $action): void
    {
        if ($action === 'update-name') {
            if (!$this->users->updateName((int)$this->user['id'], (string)($_POST['name'] ?? ''))) {
                $this->flash('Name cannot be empty.', 'error');
            } else {
                $this->user = $this->users->findById((int)$this->user['id']);
                $this->flash('Profile updated.');
            }
        } elseif ($action === 'update-profile-picture') {
            $this->updateProfilePicture();
        } elseif ($action === 'change-password') {
            $newPassword = (string)($_POST['new_password'] ?? '');
            if (strlen($newPassword) < 8) {
                $this->flash('New password must be at least 8 characters.', 'error');
            } elseif ($newPassword !== (string)($_POST['confirm_password'] ?? '')) {
                $this->flash('New password and confirmation do not match.', 'error');
            } elseif (!$this->users->changePassword(
                (int)$this->user['id'],
                (string)($_POST['current_password'] ?? ''),
                $newPassword
            )) {
                $this->flash('Current password is incorrect.', 'error');
            } else {
                $this->flash('Password changed successfully.');
            }
        }
    }

    /** Validates and saves an uploaded dashboard profile picture. */
    private function updateProfilePicture(): void
    {
        $upload = $_FILES['profile_picture'] ?? null;
        if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = is_array($upload) ? (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
            $message = match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Profile picture must be no larger than 5 MB.',
                UPLOAD_ERR_NO_FILE => 'Choose an image to upload.',
                default => 'The profile picture upload failed. Please try again.',
            };
            $this->flash($message, 'error');
            return;
        }

        $temporaryPath = (string)($upload['tmp_name'] ?? '');
        $size = (int)($upload['size'] ?? 0);
        if ($size < 1 || $size > 5 * 1024 * 1024 || !is_uploaded_file($temporaryPath)) {
            $this->flash('Choose a valid image no larger than 5 MB.', 'error');
            return;
        }

        if (!function_exists('finfo_open')) {
            $this->flash('Image validation is unavailable on this server. Contact the administrator.', 'error');
            return;
        }
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo === false) {
            $this->flash('The uploaded image could not be validated.', 'error');
            return;
        }
        $mimeType = finfo_file($fileInfo, $temporaryPath);
        finfo_close($fileInfo);
        $imageInfo = getimagesize($temporaryPath);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!is_string($mimeType) || !isset($extensions[$mimeType])
            || !is_array($imageInfo) || ($imageInfo['mime'] ?? '') !== $mimeType
            || (int)$imageInfo[0] > 6000 || (int)$imageInfo[1] > 6000) {
            $this->flash('Use a valid JPG, PNG, or WebP image.', 'error');
            return;
        }

        $uploadDirectory = dirname(__DIR__, 2) . '/public/uploads/profile';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            $this->flash('The profile picture folder could not be created on the server.', 'error');
            return;
        }
        if (!is_writable($uploadDirectory)) {
            $this->flash('The profile picture folder is not writable on the server.', 'error');
            return;
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
        $destination = $uploadDirectory . '/' . $filename;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            $this->flash('The profile picture could not be saved. Please try again.', 'error');
            return;
        }

        $previousPath = (string)($this->user['profile_image'] ?? '');
        $relativePath = 'uploads/profile/' . $filename;
        try {
            $this->users->updateProfileImage((int)$this->user['id'], $relativePath);
            $this->user = $this->users->findById((int)$this->user['id']);
        } catch (PDOException $exception) {
            if (is_file($destination) && !unlink($destination)) {
                error_log('Unable to remove unsaved profile image: ' . $destination);
            }
            throw $exception;
        }

        if (preg_match('#^uploads/profile/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $previousPath) === 1) {
            $previousFile = dirname(__DIR__, 2) . '/public/' . $previousPath;
            if (is_file($previousFile) && !unlink($previousFile)) {
                error_log('Unable to remove replaced profile image: ' . $previousFile);
            }
        }
        $this->flash('Profile picture updated.');
    }

    /** Adds or removes a moderator restriction submitted by an admin. */
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

    /** Gathers the data required to render the current dashboard page. */
    private function pageData(): array
    {
        if (in_array($this->page, ['login', 'register'], true)) {
            return [];
        }
        $data = [
            'user' => $this->user,
            'noteCount' => $this->notifications->unread(),
        ];
        if (in_array($this->page, ['home', 'posts', 'control', 'reported', 'comments', 'removed', 'users', 'mobile-reports'], true)) {
            $data['mobileAvailable'] = $this->mobile->available();
            $data['mobileCounts'] = $this->mobile->counts();
            $data['mobileCommentCounts'] = $this->page === 'comments' ? $this->mobile->commentCounts() : [];
            $data['mobileQuery'] = trim((string)($_GET['q'] ?? ''));
            $data['mobileStatus'] = $this->page === 'home'
                ? 'approved'
                : ($this->page === 'reported' ? 'pending' : (string)($_GET['status'] ?? ''));
            $data['mobilePosts'] = in_array($this->page, ['comments', 'reported', 'removed', 'mobile-reports'], true)
                ? []
                : $this->mobile->posts($data['mobileStatus'], $data['mobileQuery']);
            if ($this->page === 'home') {
                $data['mobilePosts'] = array_values(array_filter(
                    $data['mobilePosts'],
                    static fn(array $post): bool => empty($post['ai_flagged'])
                ));
            }
            $reportViews = ['current', 'dismissed', 'dismissed-comments', 'removed-posts', 'removed-comments'];
            $legacyReportStatus = (string)($_GET['status'] ?? 'pending');
            $legacyReportView = $legacyReportStatus === 'pending' ? 'current' : $legacyReportStatus;
            $requestedReportView = (string)($_GET['view'] ?? $legacyReportView);
            $data['mobileReportView'] = in_array($requestedReportView, $reportViews, true)
                ? $requestedReportView
                : 'current';
            $data['mobileRemovedType'] = $this->page === 'removed'
                ? (($_GET['type'] ?? 'posts') === 'comments' ? 'comments' : 'posts')
                : ($data['mobileReportView'] === 'removed-comments' ? 'comments' : 'posts');
            $showRemovedPosts = $this->page === 'removed'
                ? $data['mobileRemovedType'] === 'posts'
                : in_array($this->page, ['reported', 'mobile-reports'], true) && $data['mobileReportView'] === 'removed-posts';
            $showRemovedComments = $this->page === 'removed'
                ? $data['mobileRemovedType'] === 'comments'
                : in_array($this->page, ['reported', 'mobile-reports'], true) && $data['mobileReportView'] === 'removed-comments';
            $data['mobileRemovedPosts'] = $showRemovedPosts
                ? $this->mobile->removedPosts($data['mobileQuery'])
                : [];
            $data['mobileRemovedComments'] = $showRemovedComments
                ? $this->mobile->removedComments($data['mobileQuery'])
                : [];
            $data['mobileDismissedComments'] = in_array($this->page, ['reported', 'mobile-reports'], true)
                    && $data['mobileReportView'] === 'dismissed-comments'
                ? $this->mobile->dismissedComments($data['mobileQuery'])
                : [];
            if ($this->page === 'users') {
                $data['mobilePosts'] = [];
            }
            $data['mobileComments'] = $this->page === 'comments'
                ? $this->mobile->comments($data['mobileQuery'])
                : [];
            $data['mobileUsers'] = in_array($this->page, ['home', 'users'], true)
                ? $this->mobile->users($this->page === 'users' ? $data['mobileQuery'] : '')
                : [];
            $data['mobileUserFilter'] = in_array($_GET['status'] ?? '', ['active', 'suspended', 'banned'], true) ? (string)$_GET['status'] : '';
            $statusCounts = array_count_values(array_column($data['mobileUsers'], 'moderation_status'));
            $data['mobileUserCounts'] = [
                'total' => count($data['mobileUsers']),
                'suspended' => $statusCounts['suspended'] ?? 0,
                'banned' => $statusCounts['banned'] ?? 0,
            ];
            if ($this->page === 'users' && $data['mobileUserFilter'] !== '') {
                $data['mobileUsers'] = array_values(array_filter(
                    $data['mobileUsers'],
                    fn($u) => $u['moderation_status'] === $data['mobileUserFilter']
                ));
            }
            $reportStatus = $data['mobileReportView'] === 'current'
                ? 'pending'
                : $data['mobileReportView'];
            $data['mobileReports'] = in_array($this->page, ['reported', 'mobile-reports'], true)
                    && in_array($data['mobileReportView'], ['current', 'reviewed', 'resolved', 'dismissed'], true)
                ? $this->mobile->reports($reportStatus, $data['mobileQuery'])
                : [];
            $data['mobileReportStatus'] = $reportStatus;
            $data['aiModerationEnabled'] = $this->aiModeration->enabled();
            $data['aiModerationConfigured'] = $this->aiModeration->configured();
            $reportRange = (string)($_GET['report_range'] ?? '7');
            $reportDays = in_array($reportRange, ['7', '30', '365'], true) ? (int)$reportRange : 7;
            $data['reportRange'] = $reportDays;
            $data['reportCountsByDate'] = $this->page === 'home'
                ? $this->mobile->reportCountsByDate($reportDays)
                : [];
        } elseif ($this->page === 'post') {
            $id = (int)($_GET['id'] ?? 0);
            $data['post'] = $this->posts->get($id);
            if ($data['post'] !== null) {
                $data['post']['cname'] = (string)$data['post']['community'];
            }
            $data['back'] = isset($_GET['review']) ? 'control.php?status=pending' : $this->safeReferrer();
            $data['replyTo'] = trim((string)($_GET['reply'] ?? ''));
        } elseif ($this->page === 'overview') {
            $data['counts'] = $this->mobile->counts();
            $data['counts']['toApprove'] = $data['counts']['pending'];
            $data['activity'] = [];
            $reportRange = (string)($_GET['report_range'] ?? '7');
            $reportDays = in_array($reportRange, ['7', '30', '365'], true) ? (int)$reportRange : 7;
            $data['reportRange'] = $reportDays;
            $data['reportCountsByDate'] = $this->mobile->reportCountsByDate($reportDays);
        } elseif ($this->page === 'notifications') {
            $data['notifications'] = $this->notifications->list();
        } elseif ($this->page === 'logs') {
            // Supply the initial values before the browser starts polling.
            $data['activeUserCount'] = $this->mobileLoginLogs()->activeUserCount();
            $data['loginSessions'] = $this->mobileLoginLogs()->recentSessions();
        } elseif ($this->page === 'restricted') {
            $data['restrictedUsers'] = $this->restricted->list();
        } elseif ($this->page === 'comments') {
            $data['comments'] = $this->posts->comments();
        } elseif (in_array($this->page, ['mobile-feed', 'mobile-queues', 'mobile-comments'], true)) {
            $data['mobileAvailable'] = $this->mobile->available();
            $data['mobileCounts'] = $this->mobile->counts();
            $query = trim((string)($_GET['q'] ?? ''));
            $data['mobileQuery'] = $query;
            if ($this->page === 'mobile-comments') {
                $data['mobileComments'] = $this->mobile->comments($query);
            } else {
                $status = $this->page === 'mobile-queues' ? (string)($_GET['status'] ?? 'pending') : (string)($_GET['status'] ?? '');
                $data['mobilePosts'] = $this->mobile->posts($status, $query);
                $data['mobileStatus'] = $status;
            }
        } elseif ($this->page === 'user') {
            $data['mobileAvailable'] = $this->mobile->available();
            $data['mobileUserActivity'] = $data['mobileAvailable']
                ? $this->mobile->userActivity((int)($_GET['id'] ?? 0))
                : null;
        } elseif ($this->page === 'mobile-post') {
            $data['mobileAvailable'] = $this->mobile->available();
            $data['mobileCounts'] = $this->mobile->counts();
            $data['mobilePost'] = $data['mobileAvailable']
                ? $this->mobile->post((int)($_GET['id'] ?? 0), ($_GET['archive'] ?? '') === '1')
                : null;
        }
        return $data;
    }

    /** Builds the post feed and filter values for the current request. */
    private function feedData(): array
    {
        $status = (string)($_GET['status'] ?? '');
        if (!in_array($status, ['pending', 'approved', 'removed'], true)) {
            $status = '';
        }
        $query = trim((string)($_GET['q'] ?? ''));
        $slug = trim((string)($_GET['c'] ?? ''));
        $config = [
            'home' => ['Home', 'Approved posts from your moderated feed.', 'home'],
            'posts' => ['Posts', 'Every post in the moderation feed. Filter by status.', 'posts'],
            'control' => ['Control Feed', 'Pin or remove posts in the feed. Pending posts have to be checked in the queue first.', 'control'],
            'reported' => ['Reported', 'Posts that members reported. Review pending ones in the reported queue.', 'reported'],
        ];
        $title = $config[$this->page][0] ?? 'Search';
        $sub = $config[$this->page][1] ?? '';
        $posts = [];

        if ($this->page === 'home') {
            $posts = $this->posts->find('approved', null, '', null, 'hot');
        } elseif ($this->page === 'posts' || $this->page === 'control') {
            $posts = $this->posts->find($status ?: null);
        } elseif ($this->page === 'reported') {
            $posts = $this->posts->find(null, null, '', true);
        } elseif ($this->page === 'search') {
            $posts = $this->posts->find(null, null, $query);
            $title = $query !== '' ? 'Results for “' . $query . '”' : 'Search';
            $sub = $query !== '' ? count($posts) . ' posts found.' : 'Type something in the search bar above.';
        }
        return [
            'feedTitle' => $title,
            'feedSub' => $sub,
            'filter' => $status ?: 'all',
            'feedPosts' => $this->named($posts),
            'query' => $query,
            'mode' => $this->page,
            'topLikedToday' => $this->page === 'home' ? $this->posts->topLikedToday() : [],
            'reportCountsByDate' => $this->page === 'home' ? $this->posts->reportCountsByDate(7) : [],
        ];
    }

    /** Adds display names or other view-ready fields to a post list. */
    private function named(array $posts): array
    {
        foreach ($posts as &$post) {
            $post['cname'] = (string)$post['community'];
        }
        unset($post);
        return $posts;
    }

    /** Creates the hidden form field used to verify a request token. */
    private function csrfField(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
    }

    /** Saves a message to display after the next page load. */
    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }

    /** Builds a safe same-site URL for returning to the current page. */
    private function returnUrl(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($uri !== '' && str_starts_with($uri, '/') && !str_starts_with($uri, '//') && !str_contains($uri, "\r") && !str_contains($uri, "\n")) {
            return $uri;
        }
        return $this->page . '.php';
    }

    /** Returns a validated local referrer or a default dashboard page. */
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

    /** Sends a redirect response and stops processing the current request. */
    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
