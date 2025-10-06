<?php
namespace DevFramework\Core\Mail;

/**
 * Uses PHP's native mail() function. Suitable for simple deployments.
 * NOTE: For production-grade reliability consider SMTP driver (future feature).
 */
class NativeMailDriver implements MailDriverInterface
{
    public function send(MailMessage $message): array
    {
        try {
            $toHeader = $this->formatAddressList($message->to, false);
            $subject = $this->sanitizeHeader($message->subject);

            $headers = [];
            if ($message->from) {
                $headers[] = 'From: ' . $this->formatSingle($message->from);
            }
            if ($message->replyTo) {
                $headers[] = 'Reply-To: ' . $this->formatSingle($message->replyTo);
            }
            if ($message->cc) {
                $headers[] = 'Cc: ' . $this->formatAddressList($message->cc, false);
            }
            if ($message->bcc) {
                $headers[] = 'Bcc: ' . $this->formatAddressList($message->bcc, false);
            }
            foreach ($message->headers as $k => $v) {
                $headers[] = $this->sanitizeHeader($k) . ': ' . $this->sanitizeHeader($v);
            }

            $hasAttachments = !empty($message->attachments);
            $isMultipartAlt = $message->htmlBody && $message->textBody && !$hasAttachments;

            $body = '';
            if ($hasAttachments) {
                $boundaryOuter = '=_df_mail_outer_' . md5(uniqid('', true));
                $boundaryInner = '=_df_mail_inner_' . md5(uniqid('', true));
                $headers[] = 'MIME-Version: 1.0';
                $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundaryOuter . '"';
                $body .= "This is a multi-part message in MIME format.\n\n";
                $body .= '--' . $boundaryOuter . "\n";
                if ($message->htmlBody || $message->textBody) {
                    if ($message->htmlBody && $message->textBody) {
                        $body .= 'Content-Type: multipart/alternative; boundary="' . $boundaryInner . '"' . "\n\n";
                        // text part
                        $body .= '--' . $boundaryInner . "\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\n\n" . ($message->textBody ?? strip_tags($message->htmlBody)) . "\n\n";
                        // html part
                        $body .= '--' . $boundaryInner . "\nContent-Type: text/html; charset=UTF-8\nContent-Transfer-Encoding: 8bit\n\n" . $message->htmlBody . "\n\n";
                        $body .= '--' . $boundaryInner . "--\n";
                    } elseif ($message->htmlBody) {
                        $body .= 'Content-Type: text/html; charset=UTF-8\nContent-Transfer-Encoding: 8bit\n\n' . $message->htmlBody . "\n\n";
                    } else {
                        $body .= 'Content-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\n\n' . $message->textBody . "\n\n";
                    }
                }
                // attachments
                foreach ($message->attachments as $att) {
                    $body .= '--' . $boundaryOuter . "\n";
                    $body .= 'Content-Type: ' . $att['mime'] . '; name="' . $this->sanitizeHeader($att['filename']) . '"' . "\n";
                    $body .= 'Content-Transfer-Encoding: base64' . "\n";
                    $body .= 'Content-Disposition: attachment; filename="' . $this->sanitizeHeader($att['filename']) . '"' . "\n\n";
                    $body .= chunk_split($att['content']) . "\n";
                }
                $body .= '--' . $boundaryOuter . "--\n";
            } elseif ($isMultipartAlt) {
                $boundaryAlt = '=_df_mail_alt_' . md5(uniqid('', true));
                $headers[] = 'MIME-Version: 1.0';
                $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundaryAlt . '"';
                $body .= '--' . $boundaryAlt . "\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\n\n" . $message->textBody . "\n\n";
                $body .= '--' . $boundaryAlt . "\nContent-Type: text/html; charset=UTF-8\nContent-Transfer-Encoding: 8bit\n\n" . $message->htmlBody . "\n\n";
                $body .= '--' . $boundaryAlt . "--\n";
            } else {
                // Single part
                $headers[] = 'MIME-Version: 1.0';
                if ($message->htmlBody) {
                    $headers[] = 'Content-Type: text/html; charset=UTF-8';
                    $body = $message->htmlBody;
                } else {
                    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
                    $body = $message->textBody ?? '';
                }
            }

            $headersStr = implode("\r\n", $headers);

            $success = @mail($toHeader, $subject, $body, $headersStr);
            if (!$success) {
                return ['success'=>false,'error'=>'mail() returned false'];
            }
            return ['success'=>true];
        } catch (\Throwable $e) {
            return ['success'=>false,'error'=>$e->getMessage()];
        }
    }

    private function formatAddressList(array $list, bool $sanitize = true): string
    {
        return implode(', ', array_map(fn($a)=>$this->formatSingle($a, $sanitize), $list));
    }

    private function formatSingle(array $addr, bool $sanitize = true): string
    {
        $address = $sanitize ? $this->sanitizeHeader($addr['address']) : $addr['address'];
        $name = $addr['name'] ?? '';
        if ($name) {
            $name = $sanitize ? $this->sanitizeHeader($name) : $name;
            return sprintf('%s <%s>', $name, $address);
        }
        return $address;
    }

    private function sanitizeHeader(string $value): string
    {
        return trim(str_replace(["\r","\n"], ' ', $value));
    }
}

