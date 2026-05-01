<?php

declare(strict_types=1);

require __DIR__ . '/../includes/page.php';

$currentUser = requireAuthenticatedPage();
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));

    if ($displayName === '') {
        $errorMessage = 'Display name is required.';
    } elseif (strlen($displayName) > 80) {
        $errorMessage = 'Display name must be 80 characters or fewer.';
    } elseif ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errorMessage = 'Email must be a valid email address.';
    } elseif (updateUserProfile($currentUser, $displayName, $email) === null) {
        $errorMessage = 'Unable to update profile.';
    } else {
        $successMessage = 'Profile updated.';
    }
}

$profile = userProfile($currentUser) ?? defaultUserProfile($currentUser);

renderHtmlHead('Profile');
?>
<body class="app-body">
    <?php renderBackgroundElements(); ?>

    <div class="app-shell">
        <?php renderAuthenticatedNav('profile', $currentUser); ?>

        <main id="profile-page" class="glass-panel active wide-panel">
            <div class="page-heading">
                <div>
                    <p class="eyebrow">Protected page</p>
                    <h1>Profile</h1>
                    <p>Update the account fields returned by the profile API.</p>
                </div>
                <div class="avatar"><?= h(userAvatarLetter($currentUser)) ?></div>
            </div>

            <section class="content-grid two-column">
                <article class="action-card">
                    <h2>Current profile</h2>
                    <dl class="detail-list">
                        <div>
                            <dt>Username</dt>
                            <dd id="profile-username"><?= h($currentUser) ?></dd>
                        </div>
                        <div>
                            <dt>Display name</dt>
                            <dd id="profile-display-name"><?= h((string) ($profile['display_name'] ?? $currentUser)) ?></dd>
                        </div>
                        <div>
                            <dt>Email</dt>
                            <dd id="profile-email"><?= h((string) ($profile['email'] ?? '')) ?></dd>
                        </div>
                        <div>
                            <dt>Role</dt>
                            <dd id="profile-role"><?= h((string) ($profile['role'] ?? 'Premium User')) ?></dd>
                        </div>
                    </dl>
                </article>

                <article class="action-card">
                    <h2>Edit profile</h2>
                    <form id="profile-form" method="post" action="/profile/" class="stacked-form">
                        <div class="input-group">
                            <label for="display-name">Display name</label>
                            <input type="text" id="display-name" name="display_name" value="<?= h((string) ($profile['display_name'] ?? $currentUser)) ?>" required>
                        </div>
                        <div class="input-group">
                            <label for="profile-email-input">Email</label>
                            <input type="email" id="profile-email-input" name="email" value="<?= h((string) ($profile['email'] ?? '')) ?>" placeholder="name@example.com">
                        </div>
                        <?php if ($errorMessage !== ''): ?>
                            <div class="error-message"><?= h($errorMessage) ?></div>
                        <?php endif; ?>
                        <?php if ($successMessage !== ''): ?>
                            <div class="success-message"><?= h($successMessage) ?></div>
                        <?php endif; ?>
                        <button type="submit" class="btn primary-btn">Save Profile</button>
                    </form>
                </article>
            </section>
        </main>
    </div>
</body>
</html>
