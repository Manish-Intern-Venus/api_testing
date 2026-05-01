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

    private function request(string $method, string $path, array $postFields = [], ?string $cookieJar = null): array
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

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
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

    private function uniqueUsername(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(6));
    }
}
