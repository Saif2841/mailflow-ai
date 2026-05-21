<?php

namespace App\MessageHandler;

use App\Message\RequestMoreInfoMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RequestMoreInfoMessageHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(RequestMoreInfoMessage $message): void
    {
        $this->logger->info('Requested more info.', [
            'email_id' => $message->emailId,
            'decision' => $message->decision,
        ]);
    }
}
