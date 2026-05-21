<?php

namespace App\MessageHandler;

use App\Message\EscalateToHumanMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class EscalateToHumanMessageHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(EscalateToHumanMessage $message): void
    {
        $this->logger->warning('Escalated to human.', [
            'email_id' => $message->emailId,
            'decision' => $message->decision,
        ]);
    }
}
