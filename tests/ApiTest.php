<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    private string $baseUrl;
    private string $cookieJar;

    protected function setUp(): void
    {
        if (!function_exists('curl_init')) {
            $this->markTestSkipped('The PHP cURL extension is required for API tests.');
        }

        $baseUrl = getenv('BASE_URL');

        if (!is_string($baseUrl) || trim($baseUrl) === '') {
            $this->markTestSkipped('Set BASE_URL to the already-running app URL before running API tests.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'login-api-cookie-');

        if ($this->cookieJar === false) {
            throw new RuntimeException('Unable to create a cookie jar for API tests.');
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->cookieJar) && file_exists($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    public function testLoginPageLoads(): void
    {
        $result = $this->getRequest('/');

        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString('Welcome Back', $result['body']);
        $this->assertStringContainsString('login-form', $result['body']);
        $this->assertStringNotContainsString('dashboard-container', $result['body']);
    }

    public function testRegistrationPageLoads(): void
    {
        $result = $this->getRequest('/registration/');

        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString('Create Account', $result['body']);
        $this->assertStringContainsString('register-form', $result['body']);
    }

    public function testStylesheetLoads(): void
    {
        $result = $this->getRequest('/styles.css');

        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString('.glass-panel', $result['body']);
    }

    public function testLoginSuccessRedirectsAndShowsDashboardOnNextRequest(): void
    {
        $login = $this->postRequest('/', [
            'username' => 'admin',
            'password' => 'password',
        ]);

        $this->assertSame(302, $login['httpCode']);
        $this->assertRedirectsTo('/', $login['headers']);

        $dashboard = $this->getRequest('/');

        $this->assertSame(200, $dashboard['httpCode']);
        $this->assertStringContainsString('Dashboard', $dashboard['body']);
        $this->assertStringContainsString('admin', $dashboard['body']);
        $this->assertStringNotContainsString('login-form', $dashboard['body']);
    }

    public function testLoginFailureWrongPasswordKeepsClientAnonymous(): void
    {
        $result = $this->postRequest('/', [
            'username' => 'admin',
            'password' => 'wrong',
        ]);

        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString('Invalid username or password.', $result['body']);

        $nextPage = $this->getRequest('/');

        $this->assertStringContainsString('login-form', $nextPage['body']);
        $this->assertStringNotContainsString('dashboard-container', $nextPage['body']);
    }

    public function testLoginFailureNonexistentUserKeepsClientAnonymous(): void
    {
        $result = $this->postRequest('/', [
            'username' => 'doesnotexist_' . bin2hex(random_bytes(4)),
            'password' => 'anything',
        ]);

        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString('Invalid username or password.', $result['body']);
        $this->assertStringContainsString('login-form', $result['body']);
    }

    public function testLoginFailureEmptyFieldsShowsValidationError(): void
    {
        $result = $this->postRequest('/', [
            'username' => '',
            'password' => '',
        ]);

        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString('Invalid username or password.', $result['body']);
        $this->assertStringContainsString('login-form', $result['body']);
    }

    public function testSeparateClientDoesNotInheritLoginSession(): void
    {
        $login = $this->postRequest('/', [
            'username' => 'admin',
            'password' => 'password',
        ]);

        $this->assertSame(302, $login['httpCode']);

        $otherClientCookieJar = tempnam(sys_get_temp_dir(), 'login-api-other-cookie-');

        if ($otherClientCookieJar === false) {
            throw new RuntimeException('Unable to create a second cookie jar for API tests.');
        }

        try {
            $result = $this->getRequest('/', $otherClientCookieJar);
        } finally {
            if (file_exists($otherClientCookieJar)) {
                unlink($otherClientCookieJar);
            }
        }

        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString('login-form', $result['body']);
        $this->assertStringNotContainsString('dashboard-container', $result['body']);
    }

    public function testRegistrationSuccessCreatesUserThatCanLogIn(): void
    {
        $username = $this->uniqueUsername('api_user');

        $registration = $this->postRequest('/registration/', [
            'username' => $username,
            'password' => 'Strong@123',
        ]);

        $this->assertSame(200, $registration['httpCode']);
        $this->assertStringContainsString('Registration successful.', $registration['body']);

        $login = $this->postRequest('/', [
            'username' => $username,
            'password' => 'Strong@123',
        ]);

        $this->assertSame(302, $login['httpCode']);
        $this->assertRedirectsTo('/', $login['headers']);

        $dashboard = $this->getRequest('/');

        $this->assertStringContainsString('Dashboard', $dashboard['body']);
        $this->assertStringContainsString($username, $dashboard['body']);
    }

    public function testRegistrationTrimsLeadingAndTrailingUsernameSpaces(): void
    {
        $username = $this->uniqueUsername('trimmed_user');

        $registration = $this->postRequest('/registration/', [
            'username' => '  ' . $username . '  ',
            'password' => 'Strong@123',
        ]);

        $this->assertSame(200, $registration['httpCode']);
        $this->assertStringContainsString('Registration successful.', $registration['body']);

        $login = $this->postRequest('/', [
            'username' => $username,
            'password' => 'Strong@123',
        ]);

        $this->assertSame(302, $login['httpCode']);
    }

    public function testRegistrationDuplicateUsernameRejected(): void
    {
        $username = $this->uniqueUsername('duplicate_user');

        $first = $this->postRequest('/registration/', [
            'username' => $username,
            'password' => 'Strong@123',
        ]);
        $second = $this->postRequest('/registration/', [
            'username' => $username,
            'password' => 'Another@123',
        ]);

        $this->assertSame(200, $first['httpCode']);
        $this->assertStringContainsString('Registration successful.', $first['body']);
        $this->assertSame(200, $second['httpCode']);
        $this->assertStringContainsString('That username is already taken.', $second['body']);
    }

    public function testRegistrationEmptyUsernameRejected(): void
    {
        $result = $this->postRequest('/registration/', [
            'username' => '   ',
            'password' => 'Strong@123',
        ]);

        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString('Username is required.', $result['body']);
    }

    /**
     * @dataProvider weakPasswordProvider
     */
    public function testRegistrationWeakPasswordRejected(string $password): void
    {
        $result = $this->postRequest('/registration/', [
            'username' => $this->uniqueUsername('weak_password_user'),
            'password' => $password,
        ]);

        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString(
            'Password must be at least 8 characters and include at least one number and one special character.',
            $result['body']
        );
    }

    public static function weakPasswordProvider(): array
    {
        return [
            'too short' => ['Abc@12'],
            'missing number' => ['StrongPass@'],
            'missing special character' => ['Strong123'],
        ];
    }

    public function testAuthenticatedUserRedirectedAwayFromRegistration(): void
    {
        $login = $this->postRequest('/', [
            'username' => 'admin',
            'password' => 'password',
        ]);
        $registration = $this->getRequest('/registration/');

        $this->assertSame(302, $login['httpCode']);
        $this->assertSame(302, $registration['httpCode']);
        $this->assertRedirectsTo('/', $registration['headers']);
    }

    /**
     * @dataProvider protectedPageProvider
     */
    public function testProtectedPagesRedirectAnonymousUsers(string $path): void
    {
        $result = $this->getRequest($path);

        $this->assertSame(302, $result['httpCode']);
        $this->assertRedirectsTo('/', $result['headers']);
    }

    public static function protectedPageProvider(): array
    {
        return [
            'profile page' => ['/profile/'],
            'settings page' => ['/settings/'],
            'activity page' => ['/activity/'],
        ];
    }

    public function testAuthenticatedUserCanLoadPostLoginPages(): void
    {
        $login = $this->postRequest('/', [
            'username' => 'admin',
            'password' => 'password',
        ]);

        $this->assertSame(302, $login['httpCode']);

        $profile = $this->getRequest('/profile/');
        $settings = $this->getRequest('/settings/');
        $activity = $this->getRequest('/activity/');

        $this->assertSame(200, $profile['httpCode']);
        $this->assertStringContainsString('profile-page', $profile['body']);
        $this->assertStringContainsString('profile-form', $profile['body']);

        $this->assertSame(200, $settings['httpCode']);
        $this->assertStringContainsString('settings-page', $settings['body']);
        $this->assertStringContainsString('settings-form', $settings['body']);

        $this->assertSame(200, $activity['httpCode']);
        $this->assertStringContainsString('activity-page', $activity['body']);
        $this->assertStringContainsString('task-form', $activity['body']);
    }

    public function testApiSessionRequiresAuthentication(): void
    {
        $result = $this->jsonRequest('GET', '/api/session.php');
        $data = $this->decodeJson($result);

        $this->assertSame(401, $result['httpCode']);
        $this->assertSame('Authentication required.', $data['error']);
    }

    public function testApiSessionReturnsAuthenticatedUser(): void
    {
        $login = $this->postRequest('/', [
            'username' => 'admin',
            'password' => 'password',
        ]);
        $result = $this->jsonRequest('GET', '/api/session.php');
        $data = $this->decodeJson($result);

        $this->assertSame(302, $login['httpCode']);
        $this->assertSame(200, $result['httpCode']);
        $this->assertSame(true, $data['authenticated']);
        $this->assertSame('admin', $data['username']);
        $this->assertSame('admin', $data['profile']['username']);
        $this->assertSame('dark', $data['settings']['theme']);
    }

    public function testApiProfileCanBeReadAndUpdated(): void
    {
        $username = $this->registerAndLogin('profile_api_user');

        $before = $this->jsonRequest('GET', '/api/profile.php');
        $beforeData = $this->decodeJson($before);

        $this->assertSame(200, $before['httpCode']);
        $this->assertSame($username, $beforeData['profile']['username']);
        $this->assertSame($username, $beforeData['profile']['display_name']);

        $update = $this->jsonRequest('PUT', '/api/profile.php', [
            'display_name' => 'API Test Person',
            'email' => 'api-person@example.com',
        ]);
        $updateData = $this->decodeJson($update);

        $this->assertSame(200, $update['httpCode']);
        $this->assertSame('API Test Person', $updateData['profile']['display_name']);
        $this->assertSame('api-person@example.com', $updateData['profile']['email']);
    }

    public function testApiProfileRejectsInvalidEmail(): void
    {
        $this->registerAndLogin('bad_email_api_user');

        $result = $this->jsonRequest('PUT', '/api/profile.php', [
            'display_name' => 'Bad Email User',
            'email' => 'not-an-email',
        ]);
        $data = $this->decodeJson($result);

        $this->assertSame(400, $result['httpCode']);
        $this->assertSame('Email must be a valid email address.', $data['error']);
    }

    public function testApiSettingsCanBeReadAndPatched(): void
    {
        $this->registerAndLogin('settings_api_user');

        $before = $this->jsonRequest('GET', '/api/settings.php');
        $beforeData = $this->decodeJson($before);

        $this->assertSame(200, $before['httpCode']);
        $this->assertSame('dark', $beforeData['settings']['theme']);
        $this->assertSame(true, $beforeData['settings']['notifications']);

        $update = $this->jsonRequest('PATCH', '/api/settings.php', [
            'theme' => 'light',
            'notifications' => false,
            'timezone' => 'Asia/Kolkata',
        ]);
        $updateData = $this->decodeJson($update);

        $this->assertSame(200, $update['httpCode']);
        $this->assertSame('light', $updateData['settings']['theme']);
        $this->assertSame(false, $updateData['settings']['notifications']);
        $this->assertSame('Asia/Kolkata', $updateData['settings']['timezone']);
    }

    public function testApiSettingsRejectsInvalidTheme(): void
    {
        $this->registerAndLogin('invalid_settings_api_user');

        $result = $this->jsonRequest('PATCH', '/api/settings.php', [
            'theme' => 'neon',
        ]);
        $data = $this->decodeJson($result);

        $this->assertSame(400, $result['httpCode']);
        $this->assertSame('Theme must be dark, light, or system.', $data['error']);
    }

    public function testApiTasksLifecycle(): void
    {
        $this->registerAndLogin('task_api_user');

        $initial = $this->jsonRequest('GET', '/api/tasks.php');
        $initialData = $this->decodeJson($initial);

        $this->assertSame(200, $initial['httpCode']);
        $this->assertSame([], $initialData['tasks']);

        $create = $this->jsonRequest('POST', '/api/tasks.php', [
            'title' => 'Exercise task lifecycle',
        ]);
        $createdData = $this->decodeJson($create);
        $taskId = $createdData['task']['id'];

        $this->assertSame(201, $create['httpCode']);
        $this->assertSame('Exercise task lifecycle', $createdData['task']['title']);
        $this->assertSame(false, $createdData['task']['completed']);

        $list = $this->jsonRequest('GET', '/api/tasks.php');
        $listData = $this->decodeJson($list);

        $this->assertSame(200, $list['httpCode']);
        $this->assertSame($taskId, $listData['tasks'][0]['id']);

        $patch = $this->jsonRequest('PATCH', '/api/tasks.php?id=' . rawurlencode($taskId), [
            'completed' => true,
            'title' => 'Exercise patched task lifecycle',
        ]);
        $patchedData = $this->decodeJson($patch);

        $this->assertSame(200, $patch['httpCode']);
        $this->assertSame(true, $patchedData['task']['completed']);
        $this->assertSame('Exercise patched task lifecycle', $patchedData['task']['title']);

        $delete = $this->jsonRequest('DELETE', '/api/tasks.php?id=' . rawurlencode($taskId));

        $this->assertSame(204, $delete['httpCode']);
        $this->assertSame('', $delete['body']);

        $afterDelete = $this->jsonRequest('GET', '/api/tasks.php');
        $afterDeleteData = $this->decodeJson($afterDelete);

        $this->assertSame([], $afterDeleteData['tasks']);
    }

    public function testApiTasksRejectsAnonymousRequests(): void
    {
        $result = $this->jsonRequest('GET', '/api/tasks.php');
        $data = $this->decodeJson($result);

        $this->assertSame(401, $result['httpCode']);
        $this->assertSame('Authentication required.', $data['error']);
    }

    public function testApiTasksRejectsInvalidCreatePayload(): void
    {
        $this->registerAndLogin('invalid_task_api_user');

        $result = $this->jsonRequest('POST', '/api/tasks.php', [
            'title' => '',
        ]);
        $data = $this->decodeJson($result);

        $this->assertSame(400, $result['httpCode']);
        $this->assertSame('Task title is required.', $data['error']);
    }

    public function testApiTasksReturnsNotFoundForMissingTask(): void
    {
        $this->registerAndLogin('missing_task_api_user');

        $result = $this->jsonRequest('PATCH', '/api/tasks.php?id=task_missing', [
            'completed' => true,
        ]);
        $data = $this->decodeJson($result);

        $this->assertSame(404, $result['httpCode']);
        $this->assertSame('Task not found.', $data['error']);
    }

    public function testApiTasksRejectsUnsupportedMethod(): void
    {
        $this->registerAndLogin('method_api_user');

        $result = $this->jsonRequest('PUT', '/api/tasks.php', [
            'title' => 'Unsupported method',
        ]);
        $data = $this->decodeJson($result);

        $this->assertSame(405, $result['httpCode']);
        $this->assertStringContainsString('Allow: GET, POST, PATCH, DELETE', $result['headers']);
        $this->assertSame('Method not allowed.', $data['error']);
    }

    public function testPostLogoutClearsSessionAndRedirectsHome(): void
    {
        $login = $this->postRequest('/', [
            'username' => 'admin',
            'password' => 'password',
        ]);
        $logout = $this->postRequest('/logout.php');
        $afterLogout = $this->getRequest('/');

        $this->assertSame(302, $login['httpCode']);
        $this->assertSame(302, $logout['httpCode']);
        $this->assertRedirectsTo('/', $logout['headers']);
        $this->assertSame(200, $afterLogout['httpCode']);
        $this->assertStringContainsString('login-form', $afterLogout['body']);
        $this->assertStringNotContainsString('dashboard-container', $afterLogout['body']);
    }

    public function testGetLogoutRedirectsWithoutClearingSession(): void
    {
        $login = $this->postRequest('/', [
            'username' => 'admin',
            'password' => 'password',
        ]);
        $logout = $this->getRequest('/logout.php');
        $afterLogoutGet = $this->getRequest('/');

        $this->assertSame(302, $login['httpCode']);
        $this->assertSame(302, $logout['httpCode']);
        $this->assertRedirectsTo('/', $logout['headers']);
        $this->assertStringContainsString('dashboard-container', $afterLogoutGet['body']);
    }

    public function testDashboardEscapesRegisteredUsernameBeforeRendering(): void
    {
        $username = '<script>' . $this->uniqueUsername('xss_user') . '</script>';

        $registration = $this->postRequest('/registration/', [
            'username' => $username,
            'password' => 'Strong@123',
        ]);
        $login = $this->postRequest('/', [
            'username' => $username,
            'password' => 'Strong@123',
        ]);
        $dashboard = $this->getRequest('/');

        $this->assertSame(200, $registration['httpCode']);
        $this->assertStringContainsString('Registration successful.', $registration['body']);
        $this->assertSame(302, $login['httpCode']);
        $this->assertStringContainsString(htmlspecialchars($username, ENT_QUOTES, 'UTF-8'), $dashboard['body']);
        $this->assertStringNotContainsString($username, $dashboard['body']);
    }

    private function getRequest(string $path, ?string $cookieJar = null): array
    {
        return $this->request('GET', $path, [], $cookieJar);
    }

    private function postRequest(string $path, array $postFields = [], ?string $cookieJar = null): array
    {
        return $this->request('POST', $path, $postFields, $cookieJar);
    }

    private function jsonRequest(string $method, string $path, ?array $payload = null, ?string $cookieJar = null): array
    {
        $encodedPayload = null;

        if ($payload !== null) {
            $encodedPayload = json_encode($payload);

            if ($encodedPayload === false) {
                throw new RuntimeException('Unable to encode JSON payload.');
            }
        }

        return $this->request($method, $path, [], $cookieJar, $encodedPayload, [
            'Accept: application/json',
            'Content-Type: application/json',
        ]);
    }

    private function request(
        string $method,
        string $path,
        array $postFields = [],
        ?string $cookieJar = null,
        ?string $rawBody = null,
        array $headers = []
    ): array
    {
        $ch = curl_init($this->baseUrl . $path);

        if ($ch === false) {
            throw new RuntimeException('Unable to create cURL handle.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar ?? $this->cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar ?? $this->cookieJar);

        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($rawBody !== null) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        } elseif ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('API request failed: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        return [
            'httpCode' => $httpCode,
            'headers' => substr($response, 0, $headerSize),
            'body' => substr($response, $headerSize),
        ];
    }

    private function assertRedirectsTo(string $path, string $headers): void
    {
        $this->assertTrue(
            preg_match('/^Location:\s*' . preg_quote($path, '/') . '\s*$/mi', $headers) === 1,
            'Expected redirect to ' . $path . '. Headers: ' . $headers
        );
    }

    private function decodeJson(array $result): array
    {
        $decoded = json_decode($result['body'], true);

        $this->assertIsArray($decoded, 'Expected JSON response body. Body: ' . $result['body']);

        return $decoded;
    }

    private function registerAndLogin(string $prefix): string
    {
        $username = $this->uniqueUsername($prefix);
        $registration = $this->postRequest('/registration/', [
            'username' => $username,
            'password' => 'Strong@123',
        ]);
        $login = $this->postRequest('/', [
            'username' => $username,
            'password' => 'Strong@123',
        ]);

        $this->assertSame(200, $registration['httpCode']);
        $this->assertStringContainsString('Registration successful.', $registration['body']);
        $this->assertSame(302, $login['httpCode']);

        return $username;
    }

    private function uniqueUsername(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(6));
    }
}
