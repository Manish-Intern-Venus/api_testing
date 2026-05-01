<?php

declare(strict_types=1);

require __DIR__ . '/../includes/page.php';

$currentUser = requireAuthenticatedPage();
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme = (string) ($_POST['theme'] ?? '');
    $timezone = trim((string) ($_POST['timezone'] ?? ''));
    $notifications = isset($_POST['notifications']);

    if (!in_array($theme, ['dark', 'light', 'system'], true)) {
        $errorMessage = 'Theme must be dark, light, or system.';
    } elseif ($timezone === '' || strlen($timezone) > 64) {
        $errorMessage = 'Timezone is required and must be 64 characters or fewer.';
    } elseif (updateUserSettings($currentUser, [
        'theme' => $theme,
        'notifications' => $notifications,
        'timezone' => $timezone,
    ]) === null) {
        $errorMessage = 'Unable to update settings.';
    } else {
        $successMessage = 'Settings updated.';
    }
}

$settings = userSettings($currentUser) ?? defaultUserSettings();

renderHtmlHead('Settings');
?>
<body class="app-body">
    <?php renderBackgroundElements(); ?>

    <div class="app-shell">
        <?php renderAuthenticatedNav('settings', $currentUser); ?>

        <main id="settings-page" class="glass-panel active wide-panel">
            <div class="page-heading">
                <div>
                    <p class="eyebrow">Protected page</p>
                    <h1>Settings</h1>
                    <p>Change preferences that are also exposed through the settings API.</p>
                </div>
                <div class="status-pill" id="settings-current-theme"><?= h((string) ($settings['theme'] ?? 'dark')) ?></div>
            </div>

            <section class="content-grid two-column">
                <article class="action-card">
                    <h2>Preference snapshot</h2>
                    <dl class="detail-list">
                        <div>
                            <dt>Theme</dt>
                            <dd><?= h((string) ($settings['theme'] ?? 'dark')) ?></dd>
                        </div>
                        <div>
                            <dt>Notifications</dt>
                            <dd><?= !empty($settings['notifications']) ? 'Enabled' : 'Disabled' ?></dd>
                        </div>
                        <div>
                            <dt>Timezone</dt>
                            <dd><?= h((string) ($settings['timezone'] ?? 'UTC')) ?></dd>
                        </div>
                    </dl>
                </article>

                <article class="action-card">
                    <h2>Edit settings</h2>
                    <form id="settings-form" method="post" action="/settings/" class="stacked-form">
                        <div class="input-group">
                            <label for="theme">Theme</label>
                            <select id="theme" name="theme">
                                <?php foreach (['dark' => 'Dark', 'light' => 'Light', 'system' => 'System'] as $value => $label): ?>
                                    <option value="<?= h($value) ?>" <?= ($settings['theme'] ?? 'dark') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <label class="checkbox-row" for="notifications">
                            <input type="checkbox" id="notifications" name="notifications" <?= !empty($settings['notifications']) ? 'checked' : '' ?>>
                            <span>Send account notifications</span>
                        </label>
                        <div class="input-group">
                            <label for="timezone">Timezone</label>
                            <input type="text" id="timezone" name="timezone" value="<?= h((string) ($settings['timezone'] ?? 'UTC')) ?>" required>
                        </div>
                        <?php if ($errorMessage !== ''): ?>
                            <div class="error-message"><?= h($errorMessage) ?></div>
                        <?php endif; ?>
                        <?php if ($successMessage !== ''): ?>
                            <div class="success-message"><?= h($successMessage) ?></div>
                        <?php endif; ?>
                        <button type="submit" class="btn primary-btn">Save Settings</button>
                    </form>
                </article>
            </section>
        </main>
    </div>
</body>
</html>
