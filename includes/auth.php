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

    $now = gmdate('c');
    $users[$username] = [
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'profile' => [
            'display_name' => $username,
            'email' => '',
            'role' => 'Premium User',
            'created_at' => $now,
        ],
        'settings' => defaultUserSettings(),
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

function defaultUserProfile(string $username): array
{
    return [
        'username' => $username,
        'display_name' => $username,
        'email' => '',
        'role' => 'Premium User',
        'created_at' => null,
    ];
}

function userProfile(string $username): ?array
{
    $user = findUser($username);

    if ($user === null) {
        return null;
    }

    $profile = is_array($user['profile'] ?? null) ? $user['profile'] : [];

    return array_merge(defaultUserProfile($username), $profile, ['username' => $username]);
}

function updateUserProfile(string $username, string $displayName, string $email): ?array
{
    $users = loadUsers();

    if (!isset($users[$username]) || !is_array($users[$username])) {
        return null;
    }

    $existingProfile = is_array($users[$username]['profile'] ?? null) ? $users[$username]['profile'] : [];
    $users[$username]['profile'] = array_merge($existingProfile, [
        'display_name' => trim($displayName),
        'email' => trim($email),
        'role' => $existingProfile['role'] ?? 'Premium User',
        'updated_at' => gmdate('c'),
    ]);

    if (!saveUsers($users)) {
        return null;
    }

    return userProfile($username);
}

function defaultUserSettings(): array
{
    return [
        'theme' => 'dark',
        'notifications' => true,
        'timezone' => 'UTC',
    ];
}

function userSettings(string $username): ?array
{
    $user = findUser($username);

    if ($user === null) {
        return null;
    }

    $settings = is_array($user['settings'] ?? null) ? $user['settings'] : [];

    return array_merge(defaultUserSettings(), $settings);
}

function updateUserSettings(string $username, array $settings): ?array
{
    $users = loadUsers();

    if (!isset($users[$username]) || !is_array($users[$username])) {
        return null;
    }

    $currentSettings = is_array($users[$username]['settings'] ?? null)
        ? $users[$username]['settings']
        : defaultUserSettings();

    $users[$username]['settings'] = array_merge($currentSettings, $settings, [
        'updated_at' => gmdate('c'),
    ]);

    if (!saveUsers($users)) {
        return null;
    }

    return userSettings($username);
}

function taskStorePath(): string
{
    $customPath = getenv('TASK_STORE');

    if (is_string($customPath) && $customPath !== '') {
        return $customPath;
    }

    return __DIR__ . '/../data/tasks.json';
}

function ensureTaskStoreExists(): void
{
    $path = taskStorePath();
    $directory = dirname($path);

    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    if (file_exists($path)) {
        return;
    }

    saveTasks([]);
}

function loadTasks(): array
{
    ensureTaskStoreExists();

    $contents = file_get_contents(taskStorePath());
    if ($contents === false || trim($contents) === '') {
        return [];
    }

    $tasks = json_decode($contents, true);

    return is_array($tasks) ? $tasks : [];
}

function saveTasks(array $tasks): bool
{
    $storePath = taskStorePath();
    $directory = dirname($storePath);

    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $json = json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        return false;
    }

    return file_put_contents($storePath, $json . PHP_EOL, LOCK_EX) !== false;
}

function tasksForUser(string $username): array
{
    $tasks = loadTasks();
    $userTasks = $tasks[$username] ?? [];

    return is_array($userTasks) ? array_values($userTasks) : [];
}

function createTask(string $username, string $title): ?array
{
    $title = trim($title);

    if ($title === '') {
        return null;
    }

    $tasks = loadTasks();
    $userTasks = tasksForUser($username);
    $now = gmdate('c');
    $task = [
        'id' => 'task_' . bin2hex(random_bytes(8)),
        'title' => $title,
        'completed' => false,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    $userTasks[] = $task;
    $tasks[$username] = $userTasks;

    if (!saveTasks($tasks)) {
        return null;
    }

    return $task;
}

function updateTask(string $username, string $taskId, array $updates): ?array
{
    $tasks = loadTasks();
    $userTasks = tasksForUser($username);

    foreach ($userTasks as $index => $task) {
        if (!is_array($task) || ($task['id'] ?? null) !== $taskId) {
            continue;
        }

        if (array_key_exists('title', $updates)) {
            $task['title'] = trim((string) $updates['title']);
        }

        if (array_key_exists('completed', $updates)) {
            $task['completed'] = (bool) $updates['completed'];
        }

        $task['updated_at'] = gmdate('c');
        $userTasks[$index] = $task;
        $tasks[$username] = array_values($userTasks);

        if (!saveTasks($tasks)) {
            return null;
        }

        return $task;
    }

    return null;
}

function deleteTask(string $username, string $taskId): bool
{
    $tasks = loadTasks();
    $userTasks = tasksForUser($username);
    $filteredTasks = [];
    $deleted = false;

    foreach ($userTasks as $task) {
        if (is_array($task) && ($task['id'] ?? null) === $taskId) {
            $deleted = true;
            continue;
        }

        $filteredTasks[] = $task;
    }

    if (!$deleted) {
        return false;
    }

    if ($filteredTasks === []) {
        unset($tasks[$username]);
    } else {
        $tasks[$username] = array_values($filteredTasks);
    }

    return saveTasks($tasks);
}
