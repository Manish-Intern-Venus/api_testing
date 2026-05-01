<?php

declare(strict_types=1);

require __DIR__ . '/../includes/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiMethodNotAllowed(['GET']);
}

$username = apiRequireUser();

apiResponse([
    'authenticated' => true,
    'username' => $username,
    'profile' => userProfile($username),
    'settings' => userSettings($username),
]);
