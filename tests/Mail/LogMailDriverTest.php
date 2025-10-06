<?php
declare(strict_types=1);

use DevFramework\Core\Mail\Mailer;
use PHPUnit\Framework\TestCase;

final class LogMailDriverTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = sys_get_temp_dir() . '/df_mail_test_' . uniqid() . '.log';
        if (file_exists($this->logFile)) { @unlink($this->logFile); }
        Mailer::_resetForTests();
        Mailer::getInstance([
            'driver' => 'log',
            'log_path' => $this->logFile,
            'from' => ['address' => 'no-reply@test.local', 'name' => 'Test'],
            'reply_to' => ['address' => null, 'name' => null],
        ]);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) { @unlink($this->logFile); }
    }

    public function testLogDriverWritesMessage(): void
    {
        $mailer = Mailer::getInstance();
        $result = $mailer->send(fn($m)=>$m->to('user@example.com')->subject('Log Test')->text('Body text'));
        $this->assertTrue($result['success'] ?? false, 'Send should succeed');
        $this->assertFileExists($this->logFile, 'Log file should be created');
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertNotEmpty($lines, 'Log file should contain at least one line');
        $record = json_decode($lines[0], true);
        $this->assertIsArray($record);
        $this->assertEquals('Log Test', $record['subject']);
        $this->assertEquals('user@example.com', $record['to'][0]['address']);
        $this->assertEquals('Body text', $record['text']);
    }
}

