<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final class EmailReceivedMessage
{
    public function __construct(public readonly string $emailId)
    {
    }
}
