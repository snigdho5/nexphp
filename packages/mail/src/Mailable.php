<?php

declare(strict_types=1);

namespace Nex\Mail;

/**
 * A mail message object.
 *
 * Usage:
 *   (new Mailable())
 *       ->to('user@example.com', 'Jane Doe')
 *       ->subject('Welcome!')
 *       ->html('<h1>Hello!</h1>')
 *       ->text('Hello!')
 *       ->from('no-reply@app.com', 'App');
 */
class Mailable
{
    /** @var list<array{email: string, name: string}> */
    protected array $to = [];

    /** @var list<array{email: string, name: string}> */
    protected array $cc = [];

    /** @var list<array{email: string, name: string}> */
    protected array $bcc = [];

    protected array $from = ['email' => '', 'name' => ''];

    protected string $subject = '';

    protected string $htmlBody = '';

    protected string $textBody = '';

    /** @var list<array{path: string, name: string, mime: string}> */
    protected array $attachments = [];

    /** @var array<string, string> */
    protected array $headers = [];

    public function to(string $email, string $name = ''): static
    {
        $this->to[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function cc(string $email, string $name = ''): static
    {
        $this->cc[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function bcc(string $email, string $name = ''): static
    {
        $this->bcc[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function from(string $email, string $name = ''): static
    {
        $this->from = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function html(string $html): static
    {
        $this->htmlBody = $html;
        return $this;
    }

    public function text(string $text): static
    {
        $this->textBody = $text;
        return $this;
    }

    public function attach(string $filePath, string $name = '', string $mime = 'application/octet-stream'): static
    {
        $this->attachments[] = [
            'path' => $filePath,
            'name' => $name ?: basename($filePath),
            'mime' => $mime,
        ];
        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getTo(): array
    {
        return $this->to;
    }

    public function getCc(): array
    {
        return $this->cc;
    }

    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function getFrom(): array
    {
        return $this->from;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getHtmlBody(): string
    {
        return $this->htmlBody;
    }

    public function getTextBody(): string
    {
        return $this->textBody;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Override in subclasses to build the message.
     * Called by the mailer before sending.
     */
    public function build(): static
    {
        return $this;
    }
}
