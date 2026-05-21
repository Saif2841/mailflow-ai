<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final class InvoiceWorkflowMessage
{
    public function __construct(
        public readonly string $emailId,
        public readonly array $decision
    ) {
    }
}
