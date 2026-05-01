<?php

declare(strict_types=1);

require __DIR__ . '/includes/page.php';

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

if ($currentUser === null) {
    renderHtmlHead('Sign In');
    ?>
    <body>
        <?php renderBackgroundElements(); ?>

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
                    <div id="error-message" class="error-message"><?= h($errorMessage) ?></div>
                <?php endif; ?>
                <button type="submit" class="btn primary-btn">Sign In</button>
            </form>
            <p class="auth-switch">
                New here?
                <a id="show-register-btn" class="text-btn" href="/registration/">Create account</a>
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$profile = userProfile($currentUser) ?? defaultUserProfile($currentUser);
$settings = userSettings($currentUser) ?? defaultUserSettings();
$tasks = tasksForUser($currentUser);
$openTaskCount = count(array_filter($tasks, static fn (array $task): bool => ($task['completed'] ?? false) === false));
$completedTaskCount = count($tasks) - $openTaskCount;

renderHtmlHead('Dashboard');
?>
<body class="app-body">
    <?php renderBackgroundElements(); ?>

    <div class="app-shell">
        <?php renderAuthenticatedNav('dashboard', $currentUser); ?>

        <main id="dashboard-container" class="glass-panel active wide-panel">
            <div class="page-heading">
                <div>
                    <p class="eyebrow">Signed in</p>
                    <h1>Dashboard</h1>
                    <p>Account tools, protected pages, and JSON API surfaces are ready for testing.</p>
                </div>
                <div class="status-pill" id="session-status">Session active</div>
            </div>

            <section class="summary-grid" aria-label="Account summary">
                <article class="summary-card">
                    <span>Profile</span>
                    <strong id="display-username"><?= h((string) ($profile['display_name'] ?? $currentUser)) ?></strong>
                    <p><?= h((string) ($profile['role'] ?? 'Premium User')) ?></p>
                </article>
                <article class="summary-card">
                    <span>Open tasks</span>
                    <strong id="open-task-count"><?= h((string) $openTaskCount) ?></strong>
                    <p><?= h((string) $completedTaskCount) ?> completed</p>
                </article>
                <article class="summary-card">
                    <span>Preferences</span>
                    <strong id="settings-theme"><?= h((string) ($settings['theme'] ?? 'dark')) ?></strong>
                    <p><?= !empty($settings['notifications']) ? 'Notifications on' : 'Notifications off' ?></p>
                </article>
            </section>

            <section class="content-grid" aria-label="Protected actions">
                <article class="action-card">
                    <h2>Profile API</h2>
                    <p>Read and update the signed-in user profile with JSON requests.</p>
                    <code>GET /api/profile.php</code>
                    <code>PUT /api/profile.php</code>
                    <a class="inline-link" href="/profile/">Open profile page</a>
                </article>
                <article class="action-card">
                    <h2>Settings API</h2>
                    <p>Patch theme, notification, and timezone settings for the active session.</p>
                    <code>GET /api/settings.php</code>
                    <code>PATCH /api/settings.php</code>
                    <a class="inline-link" href="/settings/">Open settings page</a>
                </article>
                <article class="action-card">
                    <h2>Task API</h2>
                    <p>Create, list, complete, rename, and delete task records by HTTP method.</p>
                    <code>GET /api/tasks.php</code>
                    <code>POST/PATCH/DELETE /api/tasks.php</code>
                    <a class="inline-link" href="/activity/">Open activity page</a>
                </article>
            </section>
        </main>
    </div>
</body>
</html>
