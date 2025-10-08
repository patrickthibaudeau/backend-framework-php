<?php
namespace DevFramework\Core\Mail;

/**
 * Writes outbound email payloads to a log file for local development.
 */
class LogMailDriver implements MailDriverInterface
{
    public function __construct(private string $logPath)
    {
        if (trim($this->logPath) === '') {
            throw new \InvalidArgumentException('Mail log path must not be empty.');
        }
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    }

    public function send(MailMessage $message): array
    {
        $record = [
            'timestamp' => date('c'),
            'to' => $message->to,
            'cc' => $message->cc,
            'bcc' => $message->bcc,
            'from' => $message->from,
            'reply_to' => $message->replyTo,
            'subject' => $message->subject,
            'text' => $message->textBody,
            'html' => $message->htmlBody,
            'attachments' => array_map(fn($a)=>['filename'=>$a['filename'],'mime'=>$a['mime'],'size'=>strlen(base64_decode($a['content']))], $message->attachments),
            'headers' => $message->headers,
        ];
        // Pretty-print JSON for readability in the log file
        $entry = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;
        $ok = @file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX) !== false;
        if (!$ok) {
            return ['success'=>false,'error'=>'Unable to write mail log'];
        }
        return ['success'=>true];
    }
}

