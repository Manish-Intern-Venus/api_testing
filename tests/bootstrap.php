<?php

declare(strict_types=1);

$testStorePath = __DIR__ . '/tmp/users.test.json';

putenv('AUTH_USER_STORE=' . $testStorePath);

if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
}

$_SESSION = [];

require __DIR__ . '/../includes/auth.php';

function resetTestStore(array $users = []): void
{
    $storePath = userStorePath();

    if (file_exists($storePath)) {
        unlink($storePath);
    }

    if ($users === []) {
        $users = defaultUsers();
    }

    saveUsers($users);
    $_SESSION = [];
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertFalse(bool $condition, string $message): void
{
    assertTrue(!$condition, $message);
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ' Expected: ' . var_export($expected, true) . ' Actual: ' . var_export($actual, true)
        );
    }
}

function assertNotNull(mixed $value, string $message): void
{
    if ($value === null) {
        throw new RuntimeException($message);
    }
}
