<?php

declare(strict_types=1);

require __DIR__ . '/../includes/auth.php';

if (currentUsername() !== null) {
    header('Location: /');
    exit;
}

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '') {
        $errorMessage = 'Username is required.';
    } elseif (!passwordIsStrong($password)) {
        $errorMessage = 'Password must be at least 8 characters and include at least one number and one special character.';
    } elseif (findUser($username) !== null) {
        $errorMessage = 'That username is already taken.';
    } elseif (!registerUser($username, $password)) {
        $errorMessage = 'Unable to create your account right now.';
    } else {
        $successMessage = 'Registration successful. You can now sign in.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Secure Access</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="background-elements">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <div id="register-container" class="glass-panel active">
        <div class="panel-header">
            <h2>Create Account</h2>
            <p>Set a strong password to continue</p>
        </div>
        <form id="register-form" method="post" action="/registration/">
            <div class="input-group">
                <label for="register-username">Username</label>
                <input type="text" id="register-username" name="username" placeholder="Choose a username" required autocomplete="off">
            </div>
            <div class="input-group">
                <label for="register-password">Password</label>
                <input type="password" id="register-password" name="password" placeholder="At least 8 chars, 1 number, 1 special char" required>
            </div>
            <?php if ($errorMessage !== ''): ?>
                <div id="register-error-message" class="error-message"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($successMessage !== ''): ?>
                <div id="register-success-message" class="success-message"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <button type="submit" class="btn primary-btn">Register</button>
        </form>
        <p class="auth-switch">
            Already have an account?
            <a id="show-login-btn" class="text-btn" href="/">Back to login</a>
        </p>
    </div>
</body>
</html>
