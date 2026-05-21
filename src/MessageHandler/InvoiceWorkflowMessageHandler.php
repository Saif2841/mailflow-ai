<?php

namespace App\MessageHandler;

use App\Message\InvoiceWorkflowMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class InvoiceWorkflowMessageHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(InvoiceWorkflowMessage $message): void
    {
        $this->logger->info('Invoice workflow routed.', [
            'email_id' => $message->emailId,
            'decision' => $message->decision,
        ]);
    }
}
