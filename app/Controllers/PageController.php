<?php
declare(strict_types=1);

final class PageController
{
    private const MOBILE_ACTION_PAGES = [
        'mobile-feed', 'mobile-queues', 'mobile-reports', 'mobile-post', 'mobile-comments',
        'home', 'posts', 'control', 'reported', 'comments', 'removed', 'dismissed', 'users',
    ];

    private PDO $db;
    private UserModel $users;
    private ?MobileLoginLogModel $mobileLoginLogs = null;
    private ?array $user = null;
    private PostModel $posts;
    private NotificationModel $notifications;
    private RestrictedModel $restricted;
    private AiModerationModel $aiModeration;
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
        $this->user = isset($_SESSION['user_id'])
            ? $this->users->findById((int)$_SESSION['user_id'], (string)($_SESSION['user_role'] ?? 'moderator'))
            : null;
        if ($this->user !== null && $this->user['role'] === 'moderator'
            && (!(isset($_SESSION['moderator_session_id']))
                || !(new ModeratorAccountModel($this->db))->sessionActive(
                    (int)$_SESSION['moderator_session_id'],
                    (int)$this->user['id']
                ))) {
            $this->user = null;
        }
        if (isset($_SESSION['user_id']) && $this->user === null) {
            unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['moderator_session_id']);
            $this->flash('Your session ended. Please sign in again.', 'error');
        }

        if (in_array($this->page, ['login', 'register', 'forgot-password'], true)) {
            if ($this->user !== null) {
                $this->redirect('home.php');
            }
        } elseif ($this->user === null) {
            $this->redirect('login.php');
        } else {
            $this->initializeModels((int)$this->user['id'], (string)$this->user['role']);
            if ($this->page === 'overview') {
                $this->redirect('control.php');
            }
        }

        if (($this->user['role'] ?? '') === 'admin'
            && !in_array($this->page, ['home', 'logs', 'profile', 'moderator-activity', 'reports-overview', 'user-accounts'], true)) {
            http_response_code(403);
            exit('This page is not available in the Admin dashboard.');
        }
        if (in_array($this->page, ['moderator-activity', 'reports-overview', 'user-accounts'], true)
            && ($this->user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            exit('Only the admin can view this page.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        }
        if ($this->page === 'home' && ($this->user['role'] ?? '') === 'moderator' && isset($_GET['moderator_heartbeat'])) {
            header('Cache-Control: no-store');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
            exit;
        }
        if ($this->page === 'logs' && ($this->user['role'] ?? '') === 'admin' && isset($_GET['moderator_poll'])) {
            $sessions = (new ModeratorAccountModel($this->db))->recentSessions();
            header('Cache-Control: no-store');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['sessions' => array_map(
                static fn(array $session): array => [
                    'id' => (int)$session['id'],
                    'state' => $session['state'],
                ],
                $sessions
            )], JSON_THROW_ON_ERROR);
            exit;
        }
        if ($this->page === 'home' && ($this->user['role'] ?? '') === 'admin' && isset($_GET['moderator_poll'])) {
            $moderators = (new ModeratorAccountModel($this->db))->list();
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['moderators' => array_map(
                static fn(array $moderator): array => [
                    'id' => (int)$moderator['id'],
                    'suspended' => (bool)$moderator['suspended'],
                ],
                $moderators
            )], JSON_THROW_ON_ERROR);
            exit;
        }
        if ($this->page === 'logs' && ($this->user['role'] ?? '') !== 'admin' && isset($_GET['poll'])) {
            // Send refreshed counts and session rows to the Logs page.
            $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
            $sessionPage = $this->mobileLoginLogs()->sessionPage($requestedPage === false ? 1 : $requestedPage);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'active_users' => $this->mobileLoginLogs()->activeUserCount(),
                'sessions' => $sessionPage['sessions'],
                'current_page' => $sessionPage['current_page'],
                'total_pages' => $sessionPage['total_pages'],
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
        if (($this->user['role'] ?? '') !== 'admin') {
            $this->syncAiFlaggedPostNotifications();
        }
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
    private function initializeModels(int $userId, string $role): void
    {
        $this->posts = new PostModel($this->db, $userId, $role);
        $this->notifications = new NotificationModel($this->db, $userId, $role);
        $this->restricted = new RestrictedModel($this->db);
        $this->aiModeration = new AiModerationModel($this->db);
        $this->mobile = new MobileModerationModel($this->db, $userId, $role);
    }

    /** Creates the login-log model only when the Logs page needs it. */
    private function mobileLoginLogs(): MobileLoginLogModel
    {
        if ($this->mobileLoginLogs === null) {
            $this->mobileLoginLogs = new MobileLoginLogModel($this->db);
        }
        return $this->mobileLoginLogs;
    }

    private function loggedAction(string $action, string $targetType, int $targetId, callable $operation, string $reason = ''): bool
    {
        $this->db->beginTransaction();
        try {
            $targetLabel = null;
            if ($targetType === 'moderator') {
                $target = $this->db->prepare("SELECT email FROM admin_users WHERE id = ? AND role = 'moderator' FOR UPDATE");
                $target->execute([$targetId]);
                $targetLabel = $target->fetchColumn() ?: null;
            } elseif ($targetType === 'mobile user' && $action === 'delete-mobile-account') {
                $target = $this->db->prepare('SELECT institutional_email FROM mobile_users WHERE id = ? FOR UPDATE');
                $target->execute([$targetId]);
                $targetLabel = $target->fetchColumn() ?: null;
            }
            $changed = $operation();
            if (!$changed) {
                $this->db->rollBack();
                return false;
            }
            (new ModerationActivityModel($this->db))->record($this->user, $action, $targetType, $targetId, $reason, $targetLabel);
            $this->db->commit();
            return $changed;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    /** Adds notifications for newly AI-flagged mobile posts and comments. */
    private function syncAiFlaggedPostNotifications(): void
    {
        if ($this->mobile === null || !$this->mobile->available()) {
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
            $role = (string)($_POST['role'] ?? '');
            if ($role === 'moderator') {
                (new ModeratorAccountModel($this->db))->restoreExpiredSuspensions();
            }
            $user = $this->users->login($email, $password, $role);
            if ($user === null) {
                $this->flash('Wrong email, password, or role.', 'error');
                $this->redirect('login.php');
            }
            $moderatorSessionId = $user['role'] === 'moderator'
                ? (new ModeratorAccountModel($this->db))->startSession((int)$user['id'])
                : null;
            if ($user['role'] === 'moderator' && $moderatorSessionId === null) {
                $this->flash('Wrong email, password, or role.', 'error');
                $this->redirect('login.php');
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            if ($moderatorSessionId !== null) {
                $_SESSION['moderator_session_id'] = $moderatorSessionId;
            }
            if (!empty($user['temporary_password_login'])) {
                $this->flash('You signed in with a temporary password. Change it in Profile before it expires.', 'success');
                $this->redirect('profile.php');
            }
            $this->redirect('home.php');
        }
        if ($this->page === 'forgot-password' && $action === 'request-temporary-password') {
            $email = trim((string)($_POST['email'] ?? ''));
            $role = (string)($_POST['role'] ?? '');
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $this->flash('Enter a valid email address.', 'error');
            } elseif (!in_array($role, ['moderator', 'admin'], true)) {
                $this->flash('Select the account role you use to sign in.', 'error');
            } else {
                $this->users->sendTemporaryPassword($email, $role);
            }
            $this->redirect('forgot-password.php');
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
            if (($this->user['role'] ?? '') === 'moderator' && isset($_SESSION['moderator_session_id'])) {
                (new ModeratorAccountModel($this->db))->endSession(
                    (int)$_SESSION['moderator_session_id'],
                    (int)$this->user['id']
                );
            }
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

        if ($this->user['role'] === 'admin' && $this->page === 'user-accounts') {
            if ($action !== 'delete-mobile-account') {
                http_response_code(400);
                exit('Unsupported form action.');
            }
            $id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id === null || $id <= 0) {
                $this->flash('Select a valid mobile account.', 'error');
                $this->redirect('user-accounts.php');
            }
            $deleted = $this->loggedAction($action, 'mobile user', $id, fn(): bool => $this->mobile->deleteUserAccount($id));
            $this->flash($deleted ? 'Mobile account deleted.' : 'Mobile account was not found.', $deleted ? 'success' : 'error');
            $this->redirect('user-accounts.php');
        }

        if ($this->user['role'] === 'admin' && $this->page === 'home') {
            $moderators = new ModeratorAccountModel($this->db);
            $moderatorId = (int)($_POST['moderator_id'] ?? 0);
            $reason = trim((string)($_POST['reason'] ?? ''));
            if (in_array($action, ['suspend-moderator', 'remove-moderator'], true)
                && ($reason === '' || mb_strlen($reason) > 500)) {
                $this->flash('Enter a reason of up to 500 characters.', 'error');
                $this->redirect('home.php');
            }
            if ($action === 'suspend-moderator' || $action === 'restore-moderator') {
                $suspended = $action === 'suspend-moderator';
                $deadline = $suspended ? $this->suspensionDeadline() : null;
                $changed = $this->loggedAction($action, 'moderator', $moderatorId,
                    fn(): bool => $moderators->setSuspended($moderatorId, $suspended, $deadline), $reason);
                $message = $changed
                    ? ($action === 'suspend-moderator' ? 'Moderator suspended.' : 'Moderator restored.')
                    : 'Moderator was not found or already has that status.';
            } elseif ($action === 'remove-moderator') {
                $changed = $this->loggedAction($action, 'moderator', $moderatorId,
                    fn(): bool => $moderators->remove($moderatorId), $reason);
                $message = $changed ? 'Moderator account removed.' : 'Moderator account was not found.';
            } else {
                http_response_code(400);
                exit('Unsupported form action.');
            }
            $this->flash($message, $changed ? 'success' : 'error');
            $this->redirect('home.php');
        }

        $allowedActions = match (true) {
            in_array($this->page, self::MOBILE_ACTION_PAGES, true) => [
                'mobile-approve', 'mobile-remove', 'mobile-restore-post',
                'mobile-restore-comment-archive', 'mobile-delete-comment',
                'mobile-hide-comment', 'mobile-restore-comment', 'mobile-dismiss-comment-report',
                'mobile-restore-comment-report',
                'mobile-suspend-user', 'mobile-ban-user', 'mobile-restore-user',
                'mobile-dismiss-report', 'mobile-restore-report', 'toggle-ai-moderation',
            ],
            $this->page === 'search' => ['like', 'pin', 'restore', 'remove', 'mobile-dismiss-report'],
            $this->page === 'post' => [
                'approve', 'remove', 'like-comment', 'heart-comment', 'comment',
                'clear-post-report', 'clear-comment-report',
                'delete-comment', 'hide-comment', 'restore-comment', 'delete-post', 'edit-post', 'toggle-lock',
            ],
            $this->page === 'notifications' => ['read-all'],
            $this->page === 'profile' => ['update-name', 'update-profile-picture', 'change-password'],
            default => [],
        };
        if (!in_array($action, $allowedActions, true)) {
            http_response_code(400);
            exit('Unsupported form action.');
        }
        if (in_array($this->page, self::MOBILE_ACTION_PAGES, true)) {
            if ($action === 'toggle-ai-moderation') {
                $this->aiModeration->setEnabled((string)($_POST['enabled'] ?? '0') === '1');
                $this->flash('AI Moderation setting updated.');
            } elseif ($action === 'mobile-remove') {
                $id = (int)($_POST['post_id'] ?? 0);
                $removed = $this->loggedAction($action, 'post', $id, fn(): bool => $this->mobile->removePost($id));
                $this->flash($removed ? 'Mobile post removed and the user was notified.' : 'Mobile post was not found.', $removed ? 'success' : 'error');
            } elseif ($action === 'mobile-restore-comment-archive') {
                $id = (int)($_POST['archive_id'] ?? 0);
                $restored = $this->loggedAction($action, 'comment archive', $id, fn(): bool => $this->mobile->restoreComment($id));
                $this->flash($restored ? 'Comment restored to the mobile app.' : 'This comment could not be restored (its post may no longer exist).', $restored ? 'success' : 'error');
            } elseif ($action === 'mobile-restore-post') {
                $id = (int)($_POST['archive_id'] ?? 0);
                $restored = $this->loggedAction($action, 'post archive', $id, fn(): bool => $this->mobile->restorePost($id));
                $this->flash($restored ? 'Post restored to the mobile app.' : 'This post could not be restored.', $restored ? 'success' : 'error');
            } elseif ($action === 'mobile-approve') {
                $id = (int)($_POST['post_id'] ?? 0);
                $reason = trim((string)($_POST['reason'] ?? ''));
                $updated = $this->loggedAction($action, 'post', $id, fn(): bool => $this->mobile->setPostStatus($id, 'approved', $reason), $reason);
                $this->flash($updated ? 'Mobile post status updated.' : 'Mobile post status could not be updated.', $updated ? 'success' : 'error');
            } elseif ($action === 'mobile-delete-comment') {
                $id = (int)($_POST['comment_id'] ?? 0);
                $deleted = $this->loggedAction($action, 'comment', $id, fn(): bool => $this->mobile->removeComment($id));
                $this->flash($deleted ? 'Mobile comment deleted and the user was notified.' : 'Mobile comment was not found.', $deleted ? 'success' : 'error');
                if ($deleted && isset($_POST['report_id'])) {
                    $this->redirect('removed.php?type=comments');
                }
            } elseif ($action === 'mobile-hide-comment' || $action === 'mobile-restore-comment') {
                $status = $action === 'mobile-hide-comment' ? 'hidden' : 'approved';
                $id = (int)($_POST['comment_id'] ?? 0);
                $updated = $this->loggedAction($action, 'comment', $id, fn(): bool => $this->mobile->setCommentStatus($id, $status));
                $this->flash($updated ? 'Mobile comment status updated.' : 'Mobile comment status could not be updated.', $updated ? 'success' : 'error');
            } elseif ($action === 'mobile-dismiss-comment-report') {
                $id = (int)($_POST['report_id'] ?? 0);
                $dismissed = $this->loggedAction($action, 'comment report', $id, fn(): bool => $this->mobile->dismissCommentReport($id));
                $this->flash(
                    $dismissed ? 'Comment report dismissed.' : 'Comment report was already updated.',
                    $dismissed ? 'success' : 'error'
                );
                if ($dismissed) {
                    $this->redirect('dismissed.php');
                }
            } elseif ($action === 'mobile-restore-comment-report') {
                $id = (int)($_POST['report_id'] ?? 0);
                $restored = $this->loggedAction($action, 'comment report', $id, fn(): bool => $this->mobile->restoreCommentReport($id));
                $this->flash(
                    $restored ? 'Comment report restored to current reports.' : 'Comment report was already updated.',
                    $restored ? 'success' : 'error'
                );
                if ($restored) {
                    $this->redirect('reported.php');
                }
            } elseif (in_array($action, ['mobile-suspend-user', 'mobile-ban-user', 'mobile-restore-user'], true)) {
                $status = ['mobile-suspend-user' => 'suspended', 'mobile-ban-user' => 'banned', 'mobile-restore-user' => 'active'][$action];
                $suspendedUntil = null;
                $suspensionTimezoneOffset = null;
                if ($status === 'suspended') {
                    $suspendedUntil = $this->suspensionDeadline();
                    $suspensionTimezoneOffset = (int)$_POST['suspension_timezone_offset'];
                }
                $id = (int)($_POST['user_id'] ?? 0);
                $reason = trim((string)($_POST['reason'] ?? ''));
                $updated = $this->loggedAction($action, 'mobile user', $id, fn(): bool => $this->mobile->setUserStatus(
                    $id,
                    $status,
                    $reason,
                    $suspendedUntil,
                    $suspensionTimezoneOffset
                ), $reason);
                $this->flash(
                    $updated ? ($status === 'suspended' ? 'User suspension lift time was saved.' : 'Mobile user status updated.') : 'Mobile user status could not be updated.',
                    $updated ? 'success' : 'error'
                );
            } elseif ($action === 'mobile-dismiss-report') {
                $id = (int)($_POST['report_id'] ?? 0);
                $updated = $this->loggedAction($action, 'post report', $id, fn(): bool => $this->mobile->setReportStatus($id, 'Dismissed'));
                $this->flash($updated ? 'Mobile report dismissed.' : 'Mobile report was already updated.', $updated ? 'success' : 'error');
                if ($updated) {
                    $this->redirect('dismissed.php');
                }
            } elseif ($action === 'mobile-restore-report') {
                $id = (int)($_POST['report_id'] ?? 0);
                $updated = $this->loggedAction($action, 'post report', $id, fn(): bool => $this->mobile->setReportStatus($id, 'Pending'));
                $this->flash($updated ? 'Report restored to current reports.' : 'Mobile report was already updated.', $updated ? 'success' : 'error');
                $this->redirect($updated ? 'reported.php' : $this->returnUrl());
            }
            $this->redirect($this->returnUrl());
        }
        if ($action === 'mobile-dismiss-report') {
            $id = (int)($_POST['report_id'] ?? 0);
            $updated = $this->loggedAction($action, 'post report', $id, fn(): bool => $this->mobile->setReportStatus($id, 'Dismissed'));
            $this->flash($updated ? 'Mobile report dismissed.' : 'Mobile report was already updated.', $updated ? 'success' : 'error');
            $this->redirect($this->returnUrl());
        }

        if ($action === 'comment' && $this->user['role'] === 'moderator'
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

    /** Performs the selected moderation action on a post. */
    private function handlePostAction(string $action): void
    {
        $id = (int)($_GET['id'] ?? $_POST['post_id'] ?? 0);
        $commentId = (int)($_POST['comment_id'] ?? 0);
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
                    ? 'Comment held for moderator review after an AI flag.'
                    : 'AI check unavailable. Comment held for moderator review.');
            } elseif (isset($_GET['reply'])) {
                $this->redirect('post.php?id=' . $id . (isset($_GET['review']) ? '&review=1' : ''));
            }
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
            if (!$this->users->updateName((int)$this->user['id'], (string)($_POST['name'] ?? ''), $this->user['role'])) {
                $this->flash('Name cannot be empty.', 'error');
            } else {
                $this->user = $this->users->findById((int)$this->user['id'], $this->user['role']);
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
                $newPassword,
                $this->user['role']
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
            $this->users->updateProfileImage((int)$this->user['id'], $relativePath, $this->user['role']);
            $this->user = $this->users->findById((int)$this->user['id'], $this->user['role']);
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

    private function suspensionDeadline(): string
    {
        $submittedDateTime = (string)($_POST['suspended_until'] ?? '');
        $timezoneOffset = filter_var($_POST['suspension_timezone_offset'] ?? null, FILTER_VALIDATE_INT);
        if ($timezoneOffset === false || abs($timezoneOffset) > 840) {
            $this->flash('The browser time zone could not be verified. Refresh the page and try again.', 'error');
            $this->redirect($this->returnUrl());
        }
        $offsetFromUtc = -$timezoneOffset;
        $offset = sprintf(
            '%s%02d:%02d',
            $offsetFromUtc >= 0 ? '+' : '-',
            intdiv(abs($offsetFromUtc), 60),
            abs($offsetFromUtc) % 60
        );
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:iP', $submittedDateTime . $offset);
        if ($parsed === false
            || $parsed->format('Y-m-d\TH:i') !== $submittedDateTime
            || $parsed <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            $this->flash('Choose a suspension lift date and time in the future.', 'error');
            $this->redirect($this->returnUrl());
        }
        return $parsed->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Builds view data with the page-specific data builder. */
    private function pageData(): array
    {
        if (in_array($this->page, ['login', 'register', 'forgot-password'], true)) {
            return [];
        }
        if ($this->mobile === null) {
            throw new LogicException('Dashboard models were not initialized for this request.');
        }

        return (new PageDataBuilder(
            $this->page,
            $this->posts,
            $this->notifications,
            $this->aiModeration,
            $this->mobile,
            $this->db
        ))->build(
            $this->user,
            $_GET,
            (string)($_SERVER['HTTP_HOST'] ?? ''),
            (string)($_SERVER['HTTP_REFERER'] ?? '')
        );
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

    /** Sends a redirect response and stops processing the current request. */
    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
