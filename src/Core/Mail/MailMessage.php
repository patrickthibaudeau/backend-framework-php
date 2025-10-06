<?php
namespace DevFramework\Core\Mail;

/**
 * Fluent mail message builder supporting text/html bodies and attachments.
 */
class MailMessage
{
    public array $to = [];
    public array $cc = [];
    public array $bcc = [];
    public ?array $from = null; // ['address'=>, 'name'=>]
    public ?array $replyTo = null;
    public string $subject = '';
    public ?string $textBody = null;
    public ?string $htmlBody = null;
    /** @var array<int,array{filename:string,content:string,mime:string}> */
    public array $attachments = [];
    public array $headers = [];

    public function from(string $address, ?string $name = null): self { $this->from = ['address'=>$address,'name'=>$name]; return $this; }
    public function replyTo(string $address, ?string $name = null): self { $this->replyTo = ['address'=>$address,'name'=>$name]; return $this; }
    public function to(string $address, ?string $name = null): self { $this->to[] = ['address'=>$address,'name'=>$name]; return $this; }
    public function cc(string $address, ?string $name = null): self { $this->cc[] = ['address'=>$address,'name'=>$name]; return $this; }
    public function bcc(string $address, ?string $name = null): self { $this->bcc[] = ['address'=>$address,'name'=>$name]; return $this; }
    public function subject(string $subject): self { $this->subject = $subject; return $this; }
    public function text(string $body): self { $this->textBody = $body; return $this; }
    public function html(string $body): self { $this->htmlBody = $body; return $this; }

    /**
     * Add attachment from file path or raw data.
     * @param string $source File path or raw data (when $isData true)
     * @param string|null $filename Override filename (derived from path otherwise)
     * @param string|null $mime MIME type (default application/octet-stream)
     * @param bool $isData If true, $source is raw data
     */
    public function attach(string $source, ?string $filename = null, ?string $mime = null, bool $isData = false): self
    {
        if ($isData) {
            $data = $source;
            $name = $filename ?? 'attachment.bin';
        } else {
            if (!is_readable($source)) { throw new \RuntimeException("Attachment not readable: {$source}"); }
            $data = file_get_contents($source);
            $name = $filename ?? basename($source);
        }
        $mime = $mime ?? 'application/octet-stream';
        $this->attachments[] = [
            'filename' => $name,
            'content' => base64_encode($data),
            'mime' => $mime,
        ];
        return $this;
    }

    public function header(string $name, string $value): self { $this->headers[$name] = $value; return $this; }
}

