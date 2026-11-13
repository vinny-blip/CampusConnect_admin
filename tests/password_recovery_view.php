<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';

function expectPasswordRecoveryView(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$csrf = '';
$flash = null;
$page = 'login';
ob_start();
require dirname(__DIR__) . '/app/Views/auth.php';
$loginView = ob_get_clean();
$createAccountPosition = strpos($loginView, 'Create an account');
$forgotPasswordPosition = strpos($loginView, 'href="forgot-password.php">Forgot password?</a>');
expectPasswordRecoveryView($forgotPasswordPosition !== false, 'The login page is missing the Forgot password link.');
expectPasswordRecoveryView(
    $createAccountPosition !== false && $forgotPasswordPosition > $createAccountPosition,
    'The Forgot password link should appear below Create an account.'
);

$page = 'forgot-password';
ob_start();
require dirname(__DIR__) . '/app/Views/auth.php';
$recoveryView = ob_get_clean();
expectPasswordRecoveryView(str_contains($recoveryView, 'name="action" value="request-temporary-password"'), 'The recovery form is missing its request action.');
expectPasswordRecoveryView(str_contains($recoveryView, 'name="email" type="email"'), 'The recovery form is missing the email field.');
expectPasswordRecoveryView(str_contains($recoveryView, 'name="role" required'), 'The recovery form is missing the account role selector.');
expectPasswordRecoveryView(str_contains($recoveryView, 'expires in 30 minutes'), 'The 30-minute temporary password expiry is not explained.');
expectPasswordRecoveryView(str_contains($recoveryView, 'href="login.php">Back to login</a>'), 'The recovery page is missing its return-to-login link.');

echo "Password recovery view tests passed.\n";
