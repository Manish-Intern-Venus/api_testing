<?php

declare(strict_types=1);

require __DIR__ . '/includes/auth.php';

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (attemptLogin($username, $password)) {
        header('Location: /');
        exit;
    }

    $errorMessage = 'Invalid username or password.';
}

$currentUser = currentUsername();
$displayAvatar = $currentUser !== null ? strtoupper(substr($currentUser, 0, 1)) : 'A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Access</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="background-elements">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <?php if ($currentUser === null): ?>
        <div id="login-container" class="glass-panel active">
            <div class="panel-header">
                <h2>Welcome Back</h2>
                <p>Please enter your credentials to continue</p>
            </div>
            <form id="login-form" method="post" action="/">
                <div class="input-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="off">
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <?php if ($errorMessage !== ''): ?>
                    <div id="error-message" class="error-message"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <button type="submit" class="btn primary-btn">Sign In</button>
            </form>
            <p class="auth-switch">
                New here?
                <a id="show-register-btn" class="text-btn" href="/registration/">Create account</a>
            </p>
        </div>
    <?php else: ?>
        <div id="dashboard-container" class="glass-panel active">
            <div class="panel-header">
                <h2>Dashboard</h2>
                <p>You have successfully logged in.</p>
            </div>
            <div class="user-profile">
                <div class="avatar" id="display-avatar"><?= htmlspecialchars($displayAvatar, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="user-details">
                    <h3 id="display-username"><?= htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8') ?></h3>
                    <p>Premium User</p>
                </div>
            </div>
            <form method="post" action="/logout.php">
                <button id="logout-btn" class="btn secondary-btn" type="submit">Sign Out</button>
            </form>
        </div>
    <?php endif; ?>
</body>
</html>
