<?php

declare(strict_types=1);

namespace Nex\Mail;

use Nex\Mail\Contracts\MailerInterface;
use RuntimeException;

/**
 * SMTP mailer using raw socket communication.
 *
 * For simple use cases. For production, use a provider adapter (Mailgun, SendGrid, SES).
 */
final class SmtpMailer implements MailerInterface
{
    public function __construct(
        private readonly string $host = 'localhost',
        private readonly int $port = 587,
        private readonly string $username = '',
        private readonly string $password = '',
        private readonly bool $tls = true,
        private readonly string $fromEmail = '',
        private readonly string $fromName = '',
        private readonly int $timeout = 10,
    ) {}

    public function send(Mailable $mail): void
    {
        $mail->build();

        if (empty($mail->getTo())) {
            throw new RuntimeException('Mail has no recipients. Call ->to() before sending.');
        }

        $from = $mail->getFrom();
        $fromEmail = $from['email'] !== '' ? $from['email'] : $this->fromEmail;
        $fromName = $from['name'] !== '' ? $from['name'] : $this->fromName;

        if ($fromEmail === '') {
            throw new RuntimeException('Mail has no sender. Set a from address or configure a default on the SmtpMailer.');
        }

        // Build recipients list
        $toAddresses = array_column($mail->getTo(), 'email');

        // Build MIME message
        $boundary = 'nex_' . bin2hex(random_bytes(8));
        $headers = $this->buildHeaders($mail, $fromEmail, $fromName, $boundary);
        $body = $this->buildBody($mail, $boundary);

        // Connect and send via SMTP
        $socket = $this->connect();

        try {
            $this->smtpGreet($socket);
            $this->smtpStartTls($socket);
            $this->smtpAuth($socket);
            $this->smtpMailFrom($socket, $fromEmail);

            foreach ($toAddresses as $address) {
                $this->smtpRcptTo($socket, $address);
            }

            $this->smtpData($socket, $headers . "\r\n" . $body);
            $this->smtpQuit($socket);
        } finally {
            fclose($socket);
        }
    }

    // -------------------------------------------------------------------------
    // MIME building
    // -------------------------------------------------------------------------

    private function buildHeaders(Mailable $mail, string $fromEmail, string $fromName, string $boundary): string
    {
        $fromEmail = $this->sanitizeHeader($fromEmail);
        $fromName = $this->sanitizeHeader($fromName);

        $to = implode(', ', array_map(
            fn ($r) => $r['name'] !== ''
                ? "\"{$this->sanitizeHeader($r['name'])}\" <{$this->sanitizeHeader($r['email'])}>"
                : $this->sanitizeHeader($r['email']),
            $mail->getTo(),
        ));

        $lines = [
            "From: " . ($fromName !== '' ? "\"{$fromName}\" <{$fromEmail}>" : $fromEmail),
            "To: {$to}",
            "Subject: " . $this->sanitizeHeader($mail->getSubject()),
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "Date: " . date('r'),
            "Message-ID: <" . bin2hex(random_bytes(16)) . "@nex>",
        ];

        foreach ($mail->getHeaders() as $name => $value) {
            $safeName = $this->sanitizeHeader((string) $name);
            $safeValue = $this->sanitizeHeader((string) $value);
            $lines[] = "{$safeName}: {$safeValue}";
        }

        return implode("\r\n", $lines);
    }

    private function sanitizeHeader(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }

    private function buildBody(Mailable $mail, string $boundary): string
    {
        $parts = [];

        if ($mail->getTextBody() !== '') {
            $parts[] = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . $mail->getTextBody();
        }

        if ($mail->getHtmlBody() !== '') {
            $parts[] = "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $mail->getHtmlBody();
        }

        $parts[] = "--{$boundary}--";

        return implode("\r\n", $parts);
    }

    // -------------------------------------------------------------------------
    // SMTP protocol
    // -------------------------------------------------------------------------

    /** @return resource */
    private function connect(): mixed
    {
        $scheme = $this->tls ? 'tls' : 'tcp';
        $socket = stream_socket_client(
            "{$scheme}://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
        );

        if ($socket === false) {
            throw new RuntimeException("SMTP connection failed to {$this->host}:{$this->port} — {$errstr} (errno: {$errno})");
        }

        return $socket;
    }

    /** @param resource $socket */
    private function smtpGreet(mixed $socket): void
    {
        $this->readResponse($socket, 220);
        $this->sendCommand($socket, "EHLO nex");
        $this->readResponse($socket, 250);
    }

    /** @param resource $socket */
    private function smtpStartTls(mixed $socket): void
    {
        // TLS is handled at connection level for port 587 via tls:// scheme
    }

    /** @param resource $socket */
    private function smtpAuth(mixed $socket): void
    {
        if ($this->username === '') {
            return;
        }
        $this->sendCommand($socket, "AUTH LOGIN");
        $this->readResponse($socket, 334);
        $this->sendCommand($socket, base64_encode($this->username));
        $this->readResponse($socket, 334);
        $this->sendCommand($socket, base64_encode($this->password));
        $this->readResponse($socket, 235);
    }

    /** @param resource $socket */
    private function smtpMailFrom(mixed $socket, string $email): void
    {
        $this->sendCommand($socket, "MAIL FROM:<{$email}>");
        $this->readResponse($socket, 250);
    }

    /** @param resource $socket */
    private function smtpRcptTo(mixed $socket, string $email): void
    {
        $this->sendCommand($socket, "RCPT TO:<{$email}>");
        $this->readResponse($socket, 250);
    }

    /** @param resource $socket */
    private function smtpData(mixed $socket, string $message): void
    {
        $this->sendCommand($socket, "DATA");
        $this->readResponse($socket, 354);
        // Dot-stuff: lines starting with '.' must be doubled
        $message = str_replace("\n.", "\n..", $message);
        fwrite($socket, $message . "\r\n.\r\n");
        $this->readResponse($socket, 250);
    }

    /** @param resource $socket */
    private function smtpQuit(mixed $socket): void
    {
        $this->sendCommand($socket, "QUIT");
    }

    /** @param resource $socket */
    private function sendCommand(mixed $socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }

    /** @param resource $socket */
    private function readResponse(mixed $socket, int $expectedCode): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new RuntimeException(
                "SMTP unexpected response (expected {$expectedCode}, got {$code}): " . trim($response)
            );
        }

        return $response;
    }
}
