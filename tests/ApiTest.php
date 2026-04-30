<?php
// filepath: tests/ApiTest.php
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    private function getBaseUrl(): string
    {
        return getenv('BASE_URL') ?: 'http://host.docker.internal:8080';
    }

    public function testLoginSuccess(): void
    {
        $ch = curl_init($this->getBaseUrl() . '/login.php');
        curl_setopt($ch, CURL_POST, true);
        curl_setopt($ch, CURL_POSTFIELDS, 'username=admin&password=password');
        curl_setopt($ch, CURL_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $this->assertStringContainsString('success', $response);
    }

    public function testLoginFailure(): void
    {
        $ch = curl_init($this->getBaseUrl() . '/login.php');
        curl_setopt($ch, CURL_POST, true);
        curl_setopt($ch, CURL_POSTFIELDS, 'username=admin&password=wrong');
        curl_setopt($ch, CURL_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $this->assertStringContainsString('error', $response);
    }
}