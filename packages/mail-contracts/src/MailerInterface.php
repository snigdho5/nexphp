<?php

declare(strict_types=1);

namespace Nex\Mail\Contracts;

use Nex\Mail\Mailable;

/**
 * Mail transport contract.
 */
interface MailerInterface
{
    public function send(Mailable $mail): void;
}
