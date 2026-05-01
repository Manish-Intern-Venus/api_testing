<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$tests = [
    'default admin user is available' => function (): void {
        resetTestStore();
        $admin = findUser('admin');

        assertNotNull($admin, 'Default admin user should exist.');
        assertTrue(password_verify('password', $admin['password_hash']), 'Default admin password should match.');
    },
    'strong password policy accepts valid password' => function (): void {
        assertTrue(passwordIsStrong('Strong@123'), 'Expected strong password to pass validation.');
    },
    'strong password policy rejects weak password' => function (): void {
        assertFalse(passwordIsStrong('weakpass'), 'Expected weak password to fail validation.');
    },
    'register user stores a hashed password' => function (): void {
        resetTestStore();
        $result = registerUser('tester', 'Strong@123');
        $user = findUser('tester');

        assertTrue($result, 'User registration should succeed.');
        assertNotNull($user, 'Registered user should be persisted.');
        assertTrue(password_verify('Strong@123', $user['password_hash']), 'Stored hash should verify.');
    },
    'duplicate usernames are rejected' => function (): void {
        resetTestStore();
        registerUser('tester', 'Strong@123');

        assertFalse(registerUser('tester', 'Another@123'), 'Duplicate registration should fail.');
    },
    'login success sets the session username' => function (): void {
        resetTestStore();
        registerUser('tester', 'Strong@123');

        assertTrue(attemptLogin('tester', 'Strong@123'), 'Login should succeed with valid credentials.');
        assertSame('tester', currentUsername(), 'Session username should be set after login.');
    },
    'login failure does not set the session username' => function (): void {
        resetTestStore();
        registerUser('tester', 'Strong@123');

        assertFalse(attemptLogin('tester', 'wrong'), 'Login should fail with invalid credentials.');
        assertSame(null, currentUsername(), 'Session username should stay empty after failed login.');
    },
    'logout clears the active session' => function (): void {
        resetTestStore();
        registerUser('tester', 'Strong@123');
        attemptLogin('tester', 'Strong@123');

        logoutUser();

        assertSame(null, currentUsername(), 'Current user should be cleared after logout.');
    },
    'profile data can be updated for a user' => function (): void {
        resetTestStore();
        registerUser('tester', 'Strong@123');

        $profile = updateUserProfile('tester', 'Test Person', 'tester@example.com');

        assertNotNull($profile, 'Profile update should return updated data.');
        assertSame('tester', $profile['username'], 'Profile should retain username.');
        assertSame('Test Person', $profile['display_name'], 'Profile display name should be updated.');
        assertSame('tester@example.com', $profile['email'], 'Profile email should be updated.');
    },
    'settings data can be updated for a user' => function (): void {
        resetTestStore();
        registerUser('tester', 'Strong@123');

        $settings = updateUserSettings('tester', [
            'theme' => 'light',
            'notifications' => false,
            'timezone' => 'Asia/Kolkata',
        ]);

        assertNotNull($settings, 'Settings update should return updated data.');
        assertSame('light', $settings['theme'], 'Theme should be updated.');
        assertSame(false, $settings['notifications'], 'Notifications should be updated.');
        assertSame('Asia/Kolkata', $settings['timezone'], 'Timezone should be updated.');
    },
    'tasks can be created updated and deleted per user' => function (): void {
        resetTestStore();
        registerUser('tester', 'Strong@123');
        registerUser('other', 'Strong@123');

        $task = createTask('tester', 'Exercise task API');

        assertNotNull($task, 'Task creation should return a task.');
        assertSame('Exercise task API', $task['title'], 'Task title should be stored.');
        assertSame(false, $task['completed'], 'New tasks should be open.');
        assertSame(1, count(tasksForUser('tester')), 'Tester should have one task.');
        assertSame(0, count(tasksForUser('other')), 'Other users should not see tester tasks.');

        $updated = updateTask('tester', $task['id'], ['completed' => true]);

        assertNotNull($updated, 'Task update should return a task.');
        assertSame(true, $updated['completed'], 'Task should be completed.');
        assertTrue(deleteTask('tester', $task['id']), 'Task deletion should succeed.');
        assertSame(0, count(tasksForUser('tester')), 'Deleted task should be removed.');
    },
];

$failures = [];

foreach ($tests as $name => $test) {
    try {
        $test();
        echo "[PASS] {$name}\n";
    } catch (Throwable $exception) {
        $failures[] = "[FAIL] {$name}: " . $exception->getMessage();
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, $failure . PHP_EOL);
    }

    exit(1);
}

echo 'All auth tests passed.' . PHP_EOL;
