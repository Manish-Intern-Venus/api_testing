<?php

declare(strict_types=1);

require __DIR__ . '/../includes/api.php';

$username = apiRequireUser();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    apiResponse(['settings' => userSettings($username)]);
}

if ($method !== 'PATCH' && $method !== 'PUT') {
    apiMethodNotAllowed(['GET', 'PATCH', 'PUT']);
}

$body = apiJsonBody();
$updates = [];

if (array_key_exists('theme', $body)) {
    if (!is_string($body['theme']) || !in_array($body['theme'], ['dark', 'light', 'system'], true)) {
        apiResponse(['error' => 'Theme must be dark, light, or system.'], 400);
    }

    $updates['theme'] = $body['theme'];
}

if (array_key_exists('notifications', $body)) {
    if (!is_bool($body['notifications'])) {
        apiResponse(['error' => 'Notifications must be true or false.'], 400);
    }

    $updates['notifications'] = $body['notifications'];
}

if (array_key_exists('timezone', $body)) {
    if (!is_string($body['timezone']) || trim($body['timezone']) === '' || strlen(trim($body['timezone'])) > 64) {
        apiResponse(['error' => 'Timezone is required and must be 64 characters or fewer.'], 400);
    }

    $updates['timezone'] = trim($body['timezone']);
}

if ($updates === []) {
    apiResponse(['error' => 'At least one setting is required.'], 400);
}

$settings = updateUserSettings($username, $updates);

if ($settings === null) {
    apiResponse(['error' => 'Unable to update settings.'], 500);
}

apiResponse(['settings' => $settings]);
