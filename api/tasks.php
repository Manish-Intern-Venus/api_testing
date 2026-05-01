<?php

declare(strict_types=1);

require __DIR__ . '/../includes/api.php';

$username = apiRequireUser();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    apiResponse(['tasks' => tasksForUser($username)]);
}

if ($method === 'POST') {
    $body = apiJsonBody();
    $title = apiRequiredString($body, 'title', 'Task title', 120);
    $task = createTask($username, $title);

    if ($task === null) {
        apiResponse(['error' => 'Unable to create task.'], 500);
    }

    apiResponse(['task' => $task], 201);
}

if ($method === 'PATCH') {
    $taskId = trim((string) ($_GET['id'] ?? ''));

    if ($taskId === '') {
        apiResponse(['error' => 'Task id is required.'], 400);
    }

    $body = apiJsonBody();
    $updates = [];

    if (array_key_exists('title', $body)) {
        $updates['title'] = apiRequiredString($body, 'title', 'Task title', 120);
    }

    if (array_key_exists('completed', $body)) {
        if (!is_bool($body['completed'])) {
            apiResponse(['error' => 'Completed must be true or false.'], 400);
        }

        $updates['completed'] = $body['completed'];
    }

    if ($updates === []) {
        apiResponse(['error' => 'At least one task update is required.'], 400);
    }

    $task = updateTask($username, $taskId, $updates);

    if ($task === null) {
        apiResponse(['error' => 'Task not found.'], 404);
    }

    apiResponse(['task' => $task]);
}

if ($method === 'DELETE') {
    $taskId = trim((string) ($_GET['id'] ?? ''));

    if ($taskId === '') {
        apiResponse(['error' => 'Task id is required.'], 400);
    }

    if (!deleteTask($username, $taskId)) {
        apiResponse(['error' => 'Task not found.'], 404);
    }

    apiNoContent();
}

apiMethodNotAllowed(['GET', 'POST', 'PATCH', 'DELETE']);
