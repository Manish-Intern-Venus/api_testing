<?php
// filepath: tests/ApiTest.php
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    private function getBaseUrl(): string
    {
        return getenv('BASE_URL') ?: 'http://host.docker.internal:8080';
    }

    /**
     * Helper: create a cURL handle for a POST request.
     */
    private function postRequest(string $path, string $postFields): array
    {
        $ch = curl_init($this->getBaseUrl() . $path);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Do NOT follow redirects — we want to inspect the Location header.
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        // Share cookies between requests within a test (for session).
        curl_setopt($ch, CURLOPT_COOKIEJAR, '');
        curl_setopt($ch, CURLOPT_COOKIEFILE, '');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($ch);

        return [
            'httpCode' => $httpCode,
            'headers'  => $headers,
            'body'     => $body,
        ];
    }

    /**
     * Helper: create a cURL handle for a GET request.
     */
    private function getRequest(string $path): array
    {
        $ch = curl_init($this->getBaseUrl() . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($ch);

        return [
            'httpCode' => $httpCode,
            'headers'  => $headers,
            'body'     => $body,
        ];
    }

    // ---------------------------------------------------------------
    // Login tests — POST /  (index.php handles login at root)
    // ---------------------------------------------------------------

    public function testLoginPageLoads(): void
    {
        $result = $this->getRequest('/');
        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString('Welcome Back', $result['body']);
        $this->assertStringContainsString('login-form', $result['body']);
    }

    public function testLoginSuccess(): void
    {
        // The default admin user has password "password".
        $result = $this->postRequest('/', 'username=admin&password=password');

        // Successful login issues a 302 redirect to "/"
        $this->assertSame(302, $result['httpCode']);
        $this->assertStringContainsString('Location: /', $result['headers']);
    }

    public function testLoginFailureWrongPassword(): void
    {
        $result = $this->postRequest('/', 'username=admin&password=wrong');

        // Failed login re-renders the page with an error message.
        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString('Invalid username or password.', $result['body']);
    }

    public function testLoginFailureNonexistentUser(): void
    {
        $result = $this->postRequest('/', 'username=doesnotexist&password=anything');

        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString('Invalid username or password.', $result['body']);
    }

    public function testLoginFailureEmptyFields(): void
    {
        $result = $this->postRequest('/', 'username=&password=');

        // Empty credentials should not log in — the page re-renders.
        $this->assertSame(200, $result['httpCode']);
        // Should show the login form (no redirect).
        $this->assertStringContainsString('login-form', $result['body']);
    }

    // ---------------------------------------------------------------
    // Registration tests — POST /registration/
    // ---------------------------------------------------------------

    public function testRegistrationPageLoads(): void
    {
        $result = $this->getRequest('/registration/');
        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString('Create Account', $result['body']);
        $this->assertStringContainsString('register-form', $result['body']);
    }

    public function testRegistrationWeakPasswordRejected(): void
    {
        $result = $this->postRequest('/registration/', 'username=newuser&password=weak');

        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString(
            'Password must be at least 8 characters and include at least one number and one special character.',
            $result['body']
        );
    }

    public function testRegistrationEmptyUsernameRejected(): void
    {
        $result = $this->postRequest('/registration/', 'username=&password=Strong@123');

        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString('Username is required.', $result['body']);
    }

    public function testRegistrationDuplicateUsernameRejected(): void
    {
        // "admin" already exists in the default data store.
        $result = $this->postRequest('/registration/', 'username=admin&password=Strong@123');

        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString('That username is already taken.', $result['body']);
    }

    public function testRegistrationSuccess(): void
    {
        // Use a unique username to avoid collisions.
        $unique = 'testuser_' . time();
        $result = $this->postRequest('/registration/', 'username=' . $unique . '&password=Strong@123');

        $this->assertSame(200, $result['httpCode']);
        $this->assertStringContainsString('Registration successful.', $result['body']);
    }

    // ---------------------------------------------------------------
    // Logout test — POST /logout.php
    // ---------------------------------------------------------------

    public function testLogoutRedirectsToRoot(): void
    {
        $result = $this->postRequest('/logout.php', '');

        // logout.php always redirects to "/" regardless of method.
        $this->assertSame(302, $result['httpCode']);
        $this->assertStringContainsString('Location: /', $result['headers']);
    }
}