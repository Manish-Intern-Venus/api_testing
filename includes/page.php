<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function requireAuthenticatedPage(): string
{
    $username = currentUsername();

    if ($username === null) {
        header('Location: /');
        exit;
    }

    return $username;
}

function userAvatarLetter(string $username): string
{
    return strtoupper(substr($username, 0, 1));
}

function renderBackgroundElements(): void
{
    echo '<div class="background-elements">';
    echo '<div class="blob blob-1"></div>';
    echo '<div class="blob blob-2"></div>';
    echo '</div>';
}

function renderAuthenticatedNav(string $activePage, string $username): void
{
    $profile = userProfile($username) ?? defaultUserProfile($username);
    $displayName = $profile['display_name'] ?? $username;

    $links = [
        'dashboard' => ['href' => '/', 'label' => 'Dashboard'],
        'profile' => ['href' => '/profile/', 'label' => 'Profile'],
        'settings' => ['href' => '/settings/', 'label' => 'Settings'],
        'activity' => ['href' => '/activity/', 'label' => 'Activity'],
    ];

    echo '<aside class="side-nav" id="dashboard-nav">';
    echo '<div class="nav-brand">';
    echo '<div class="avatar avatar-sm">' . h(userAvatarLetter($username)) . '</div>';
    echo '<div><strong>' . h($displayName) . '</strong><span>' . h($username) . '</span></div>';
    echo '</div>';
    echo '<nav class="nav-links" aria-label="Authenticated pages">';

    foreach ($links as $key => $link) {
        $className = $activePage === $key ? 'nav-link active' : 'nav-link';
        echo '<a class="' . h($className) . '" id="' . h($key) . '-page-link" href="' . h($link['href']) . '">' . h($link['label']) . '</a>';
    }

    echo '</nav>';
    echo '<form class="nav-logout" method="post" action="/logout.php">';
    echo '<button id="logout-btn" class="btn secondary-btn" type="submit">Sign Out</button>';
    echo '</form>';
    echo '</aside>';
}

function renderHtmlHead(string $title): void
{
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . h($title) . ' - Secure Access</title>';
    echo '<link rel="stylesheet" href="/styles.css">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
    echo '</head>';
}
