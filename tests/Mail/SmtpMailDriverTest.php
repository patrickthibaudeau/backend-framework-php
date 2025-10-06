<?php
declare(strict_types=1);

use DevFramework\Core\Mail\Mailer;
use PHPUnit\Framework\TestCase;

final class SmtpMailDriverTest extends TestCase
{
    protected function setUp(): void
    {
        Mailer::_resetForTests();
    }

    public function testMissingHostReturnsError(): void
    {
        $mailer = Mailer::getInstance([
            'driver' => 'smtp',
            'from' => ['address' => 'no-reply@test.local', 'name' => 'Test'],
            'reply_to' => ['address' => null, 'name' => null],
            'smtp' => [
                'host' => '', // intentionally missing
                'port' => 587,
                'username' => null,
                'password' => null,
                'encryption' => 'tls',
                'timeout' => 3,
            ],
        ]);
        $result = $mailer->send(fn($m)=>$m->to('user@example.com')->subject('SMTP Test')->text('Body'));
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('host not configured', strtolower($result['error'] ?? ''));
    }
}

