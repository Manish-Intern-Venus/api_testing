<?php

declare(strict_types=1);

require __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logoutUser();
}

header('Location: /');
exit;
