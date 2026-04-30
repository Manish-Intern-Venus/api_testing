<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function userStorePath(): string
{
    $customPath = getenv('AUTH_USER_STORE');

    if (is_string($customPath) && $customPath !== '') {
        return $customPath;
    }

    return __DIR__ . '/../data/users.json';
}

function defaultUsers(): array
{
    return [
        'admin' => [
            'password_hash' => '$2y$12$Qs7bq1ALihcv/P0Elv8x0OufaxFVsw7EWsbsz5n99jVXYpB8hOnya',
        ],
    ];
}

function ensureUserStoreExists(): void
{
    $path = userStorePath();
    $directory = dirname($path);

    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    if (file_exists($path)) {
        return;
    }

    saveUsers(defaultUsers());
}

function loadUsers(): array
{
    ensureUserStoreExists();

    $storePath = userStorePath();

    $contents = file_get_contents($storePath);
    if ($contents === false || trim($contents) === '') {
        return [];
    }

    $users = json_decode($contents, true);

    return is_array($users) ? $users : [];
}

function saveUsers(array $users): bool
{
    $storePath = userStorePath();
    $directory = dirname($storePath);

    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $json = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        return false;
    }

    return file_put_contents($storePath, $json . PHP_EOL, LOCK_EX) !== false;
}

function findUser(string $username): ?array
{
    $users = loadUsers();

    return $users[$username] ?? null;
}

function registerUser(string $username, string $password): bool
{
    $users = loadUsers();

    if (isset($users[$username])) {
        return false;
    }

    $users[$username] = [
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ];

    return saveUsers($users);
}

function passwordIsStrong(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/\d/', $password) === 1
        && preg_match('/[^A-Za-z0-9]/', $password) === 1;
}

function attemptLogin(string $username, string $password): bool
{
    $user = findUser($username);

    if ($user === null || !isset($user['password_hash'])) {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['username'] = $username;

    return true;
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies') && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function currentUsername(): ?string
{
    if (!isset($_SESSION['username']) || !is_string($_SESSION['username'])) {
        return null;
    }

    return $_SESSION['username'];
}
