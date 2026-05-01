<?php

declare(strict_types=1);

require __DIR__ . '/../includes/api.php';

$username = apiRequireUser();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    apiResponse(['profile' => userProfile($username)]);
}

if ($method !== 'PUT' && $method !== 'PATCH') {
    apiMethodNotAllowed(['GET', 'PUT', 'PATCH']);
}

$body = apiJsonBody();
$displayName = apiRequiredString($body, 'display_name', 'Display name', 80);
$email = isset($body['email']) && is_string($body['email']) ? trim($body['email']) : '';

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    apiResponse(['error' => 'Email must be a valid email address.'], 400);
}

$profile = updateUserProfile($username, $displayName, $email);

if ($profile === null) {
    apiResponse(['error' => 'Unable to update profile.'], 500);
}

apiResponse(['profile' => $profile]);
