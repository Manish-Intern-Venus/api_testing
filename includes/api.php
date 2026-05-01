<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function apiResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function apiNoContent(): void
{
    http_response_code(204);
    exit;
}

function apiMethodNotAllowed(array $allowedMethods): void
{
    header('Allow: ' . implode(', ', $allowedMethods));
    apiResponse(['error' => 'Method not allowed.'], 405);
}

function apiRequireUser(): string
{
    $username = currentUsername();

    if ($username === null) {
        apiResponse(['error' => 'Authentication required.'], 401);
    }

    return $username;
}

function apiJsonBody(): array
{
    $rawBody = file_get_contents('php://input');

    if ($rawBody === false || trim($rawBody) === '') {
        return [];
    }

    $decoded = json_decode($rawBody, true);

    if (!is_array($decoded)) {
        apiResponse(['error' => 'Invalid JSON body.'], 400);
    }

    return $decoded;
}

function apiRequiredString(array $body, string $field, string $label, int $maxLength = 120): string
{
    $value = $body[$field] ?? null;

    if (!is_string($value)) {
        apiResponse(['error' => $label . ' is required.'], 400);
    }

    $value = trim($value);

    if ($value === '') {
        apiResponse(['error' => $label . ' is required.'], 400);
    }

    if (strlen($value) > $maxLength) {
        apiResponse(['error' => $label . ' must be ' . $maxLength . ' characters or fewer.'], 400);
    }

    return $value;
}
