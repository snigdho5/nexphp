<?php

declare(strict_types=1);

namespace Nex\Mail;

use Nex\Mail\Contracts\MailerInterface;
use RuntimeException;

/**
 * Log mailer — writes emails to a file instead of sending.
 * Use in development and tests.
 */
final class LogMailer implements MailerInterface
{
    /** @var list<Mailable> */
    private array $sent = [];

    public function __construct(
        private readonly string $logPath = '',
    ) {}

    public function send(Mailable $mail): void
    {
        $mail->build();

        $this->sent[] = $mail;

        if ($this->logPath !== '') {
            $entry = sprintf(
                "[%s] MAIL To: %s | Subject: %s\n%s\n%s\n---\n",
                date('Y-m-d H:i:s'),
                implode(', ', array_column($mail->getTo(), 'email')),
                $mail->getSubject(),
                $mail->getHtmlBody(),
                $mail->getTextBody(),
            );
            file_put_contents($this->logPath, $entry, FILE_APPEND);
        }
    }

    /** @return list<Mailable> */
    public function getSentMails(): array
    {
        return $this->sent;
    }

    public function getLastSentMail(): ?Mailable
    {
        return !empty($this->sent) ? end($this->sent) : null;
    }

    public function reset(): void
    {
        $this->sent = [];
    }
}
