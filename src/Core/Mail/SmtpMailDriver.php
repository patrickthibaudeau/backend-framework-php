<?php
namespace DevFramework\Core\Mail;

/**
 * SMTP mail driver implementing a minimal subset of RFC 5321/5322 needed for framework emails.
 * Supports: AUTH LOGIN, STARTTLS, implicit SSL, plain, attachments, text+html alternative bodies.
 * Configuration array keys (mail.smtp.*): host, port, username, password, encryption (tls|ssl|none), timeout.
 */
class SmtpMailDriver implements MailDriverInterface
{
    private string $host;
    private int $port;
    private ?string $username;
    private ?string $password;
    private string $encryption; // tls, ssl, none
    private int $timeout;
    private string $ehloDomain;

    public function __construct(array $smtpConfig, array $mailConfig = [])
    {
        $this->host = (string)($smtpConfig['host'] ?? '');
        $this->port = (int)($smtpConfig['port'] ?? 587);
        $this->username = $smtpConfig['username'] ?? null;
        $this->password = $smtpConfig['password'] ?? null;
        $this->encryption = strtolower((string)($smtpConfig['encryption'] ?? 'tls'));
        $this->timeout = (int)($smtpConfig['timeout'] ?? 10);
        $appUrl = $mailConfig['app_url'] ?? (function_exists('config') ? (config('app.url') ?? null) : null);
        $this->ehloDomain = $this->deriveEhloDomain($appUrl);
    }

    public function send(MailMessage $message): array
    {
        if (!$this->host) {
            return ['success'=>false,'error'=>'SMTP host not configured'];
        }
        if (!$message->from) {
            return ['success'=>false,'error'=>'From address required for SMTP'];
        }
        try {
            $socket = $this->connect();
            if (!$socket) { return ['success'=>false,'error'=>'Unable to open SMTP connection']; }
            $this->expect($socket, [220]);
            $this->ehlo($socket);
            if ($this->encryption === 'tls') {
                $this->write($socket, "STARTTLS\r\n");
                $this->expect($socket, [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('Failed to enable TLS');
                }
                $this->ehlo($socket); // re-issue EHLO after STARTTLS
            }
            if ($this->username) { $this->authLogin($socket); }

            $from = $message->from['address'];
            $this->write($socket, 'MAIL FROM:<'.$this->sanitizeEmail($from).">\r\n");
            $this->expect($socket, [250]);

            foreach (['to','cc','bcc'] as $listName) {
                foreach ($message->{$listName} as $addr) {
                    $this->write($socket, 'RCPT TO:<'.$this->sanitizeEmail($addr['address']).">\r\n");
                    $this->expect($socket, [250,251]);
                }
            }

            $this->write($socket, "DATA\r\n");
            $this->expect($socket, [354]);

            [$headers, $body] = $this->buildMime($message);
            $data = $headers."\r\n\r\n".$this->dotStuff($body)."\r\n.\r\n";
            $this->write($socket, $data);
            $this->expect($socket, [250]);
            $this->write($socket, "QUIT\r\n");
            fclose($socket);
            return ['success'=>true];
        } catch (\Throwable $e) {
            return ['success'=>false,'error'=>$e->getMessage()];
        }
    }

    // --- Connection & protocol helpers ---------------------------------------------------

    private function connect()
    {
        $host = $this->host;
        $port = $this->port;
        $transport = $host;
        if ($this->encryption === 'ssl') {
            if (!str_starts_with($transport, 'ssl://')) { $transport = 'ssl://'.$transport; }
        }
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ]
        ]);
        $socket = @stream_socket_client($transport.':'.$port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) { throw new \RuntimeException("SMTP connect failed: $errstr ($errno)"); }
        stream_set_timeout($socket, $this->timeout);
        return $socket;
    }

    private function ehlo($socket): void
    {
        $this->write($socket, 'EHLO '.$this->ehloDomain."\r\n");
        $this->expect($socket, [250]);
    }

    private function authLogin($socket): void
    {
        $this->write($socket, "AUTH LOGIN\r\n");
        $this->expect($socket, [334]);
        $this->write($socket, base64_encode($this->username)."\r\n");
        $this->expect($socket, [334]);
        $this->write($socket, base64_encode($this->password)."\r\n");
        $this->expect($socket, [235]);
    }

    private function write($socket, string $data): void
    {
        $len = strlen($data);
        $written = 0;
        while ($written < $len) {
            $n = fwrite($socket, substr($data, $written));
            if ($n === false) { throw new \RuntimeException('Failed writing to SMTP socket'); }
            $written += $n;
        }
    }

    private function expect($socket, array $codes): void
    {
        $response = '';
        while (true) {
            $line = fgets($socket, 8192);
            if ($line === false) { throw new \RuntimeException('SMTP read failed'); }
            $response .= $line;
            // Multi-line if 4th char is '-' keep reading
            if (strlen($line) >= 4 && $line[3] === ' ') {
                $code = (int)substr($line, 0, 3);
                if (!in_array($code, $codes, true)) {
                    throw new \RuntimeException('Unexpected SMTP response code '.$code.' (wanted '.implode(',', $codes).'): '.trim($response));
                }
                return; // success
            }
        }
    }

    private function sanitizeEmail(string $email): string
    {
        return trim(str_replace(["\r","\n"], '', $email));
    }

    private function deriveEhloDomain(?string $appUrl): string
    {
        if ($appUrl && filter_var($appUrl, FILTER_VALIDATE_URL)) {
            $host = parse_url($appUrl, PHP_URL_HOST);
            if ($host) { return $host; }
        }
        return 'localhost';
    }

    // --- MIME building (mirrors NativeMailDriver logic) -----------------------------------

    /**
     * @return array{0:string,1:string} headers(without final CRLF CRLF) and body (without trailing dot line)
     */
    private function buildMime(MailMessage $message): array
    {
        $headers = [];
        $headers[] = 'Date: '.gmdate('D, d M Y H:i:s O');
        $headers[] = 'Message-ID: <'.bin2hex(random_bytes(16)).'@'.$this->ehloDomain.'>';
        $headers[] = 'From: '.$this->formatSingle($message->from);
        if ($message->replyTo) { $headers[] = 'Reply-To: '.$this->formatSingle($message->replyTo); }
        if (!empty($message->to)) { $headers[] = 'To: '.$this->formatAddressList($message->to); }
        if (!empty($message->cc)) { $headers[] = 'Cc: '.$this->formatAddressList($message->cc); }
        $headers[] = 'Subject: '.$this->sanitizeHeader($message->subject);
        $headers[] = 'MIME-Version: 1.0';
        foreach ($message->headers as $k=>$v) { $headers[] = $this->sanitizeHeader($k).': '.$this->sanitizeHeader($v); }

        $hasAttachments = !empty($message->attachments);
        $isMultipartAlt = $message->htmlBody && $message->textBody && !$hasAttachments;
        $body = '';
        if ($hasAttachments) {
            $boundaryOuter = '=_df_mail_outer_'.md5(uniqid('', true));
            $boundaryInner = '=_df_mail_inner_'.md5(uniqid('', true));
            $headers[] = 'Content-Type: multipart/mixed; boundary="'.$boundaryOuter.'"';
            $body .= 'This is a multi-part message in MIME format.'; // first line
            $body .= "\r\n\r\n--$boundaryOuter\r\n";
            if ($message->htmlBody || $message->textBody) {
                if ($message->htmlBody && $message->textBody) {
                    $body .= 'Content-Type: multipart/alternative; boundary="'.$boundaryInner.'"' . "\r\n\r\n";
                    // text part
                    $body .= '--'.$boundaryInner."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".($message->textBody ?? strip_tags($message->htmlBody))."\r\n\r\n";
                    // html part
                    $body .= '--'.$boundaryInner."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".$message->htmlBody."\r\n\r\n";
                    $body .= '--'.$boundaryInner."--\r\n";
                } elseif ($message->htmlBody) {
                    $body .= 'Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n'.$message->htmlBody."\r\n\r\n";
                } else {
                    $body .= 'Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n'.$message->textBody."\r\n\r\n";
                }
            }
            foreach ($message->attachments as $att) {
                $body .= '--'.$boundaryOuter."\r\n";
                $body .= 'Content-Type: '.$att['mime'].'; name="'.$this->sanitizeHeader($att['filename']).'"' . "\r\n";
                $body .= 'Content-Transfer-Encoding: base64' . "\r\n";
                $body .= 'Content-Disposition: attachment; filename="'.$this->sanitizeHeader($att['filename']).'"' . "\r\n\r\n";
                $body .= $this->wrapBase64($att['content'])."\r\n"; // already base64 encoded content
            }
            $body .= '--'.$boundaryOuter."--\r\n";
        } elseif ($isMultipartAlt) {
            $boundaryAlt = '=_df_mail_alt_'.md5(uniqid('', true));
            $headers[] = 'Content-Type: multipart/alternative; boundary="'.$boundaryAlt.'"';
            $body .= '--'.$boundaryAlt."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".$message->textBody."\r\n\r\n";
            $body .= '--'.$boundaryAlt."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".$message->htmlBody."\r\n\r\n";
            $body .= '--'.$boundaryAlt."--\r\n";
        } else {
            if ($message->htmlBody) {
                $headers[] = 'Content-Type: text/html; charset=UTF-8';
                $body = $message->htmlBody."\r\n";
            } else {
                $headers[] = 'Content-Type: text/plain; charset=UTF-8';
                $body = ($message->textBody ?? '')."\r\n";
            }
        }
        return [implode("\r\n", $headers), rtrim($body, "\r\n")];
    }

    private function formatAddressList(array $list): string
    {
        return implode(', ', array_map(fn($a) => $this->formatSingle($a), $list));
    }

    private function formatSingle(array $addr): string
    {
        $email = $this->sanitizeHeader($addr['address']);
        $name = $addr['name'] ?? '';
        if ($name) {
            $name = $this->sanitizeHeader($name);
            return $name.' <'.$email.'>';
        }
        return $email;
    }

    private function sanitizeHeader(string $value): string
    {
        return trim(str_replace(["\r","\n"], ' ', $value));
    }

    private function wrapBase64(string $b64, int $lineLength = 76): string
    {
        return trim(chunk_split($b64, $lineLength, "\r\n"));
    }

    private function dotStuff(string $body): string
    {
        // Ensure CRLF line endings first
        $body = str_replace(["\r\n","\r","\n"], "\n", $body);
        $lines = explode("\n", $body);
        foreach ($lines as &$l) {
            if (isset($l[0]) && $l[0] === '.') { $l = '.'.$l; }
        }
        return implode("\r\n", $lines);
    }
}

