<?php

declare(strict_types=1);

namespace Nex\Tests\Mail;

use Nex\Mail\LogMailer;
use Nex\Mail\Mailable;
use PHPUnit\Framework\TestCase;

final class WelcomeMail extends Mailable
{
    public function __construct(private readonly string $name) {}

    public function build(): static
    {
        return $this
            ->subject("Welcome, {$this->name}!")
            ->html("<p>Welcome to NexPHP, {$this->name}</p>")
            ->text("Welcome to NexPHP, {$this->name}");
    }
}

final class MailTest extends TestCase
{
    private string $logPath;
    private LogMailer $mailer;

    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/nex_mail_log_' . uniqid() . '.log';
        $this->mailer = new LogMailer($this->logPath);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }
    }

    public function test_send_mailable_with_log_mailer(): void
    {
        $mail = (new WelcomeMail('Alice'))
            ->to('alice@example.com', 'Alice')
            ->from('hello@example.com', 'NexPHP');

        $this->mailer->send($mail);

        $sent = $this->mailer->getSentMails();
        $this->assertCount(1, $sent);
        $this->assertSame('Welcome, Alice!', $sent[0]->getSubject());
        $this->assertSame([['email' => 'alice@example.com', 'name' => 'Alice']], $sent[0]->getTo());
        $this->assertSame(['email' => 'hello@example.com', 'name' => 'NexPHP'], $sent[0]->getFrom());
        $this->assertStringContainsString('Welcome to NexPHP, Alice', $sent[0]->getHtmlBody());

        $this->assertFileExists($this->logPath);
        $logContent = file_get_contents($this->logPath);
        $this->assertStringContainsString('MAIL To: alice@example.com', $logContent);
        $this->assertStringContainsString('Subject: Welcome, Alice!', $logContent);
    }

    public function test_mailable_attachments_and_headers(): void
    {
        $mail = (new Mailable())
            ->to('bob@example.com')
            ->subject('Invoice')
            ->header('X-Custom-Header', 'NexValue')
            ->attach('/tmp/fake_invoice.pdf', 'invoice.pdf', 'application/pdf');

        $this->assertSame([['email' => 'bob@example.com', 'name' => '']], $mail->getTo());
        $this->assertSame(['X-Custom-Header' => 'NexValue'], $mail->getHeaders());
        $this->assertCount(1, $mail->getAttachments());
        $this->assertSame('invoice.pdf', $mail->getAttachments()[0]['name']);
        $this->assertSame('application/pdf', $mail->getAttachments()[0]['mime']);
    }

    public function test_reset_clears_sent_mails(): void
    {
        $mail = (new Mailable())->to('test@example.com')->subject('Test');
        $this->mailer->send($mail);
        $this->assertCount(1, $this->mailer->getSentMails());

        $this->mailer->reset();
        $this->assertCount(0, $this->mailer->getSentMails());
    }
}
