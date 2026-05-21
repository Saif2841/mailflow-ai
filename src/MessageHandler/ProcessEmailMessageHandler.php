<?php

namespace App\MessageHandler;

use App\Message\ProcessEmailMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessEmailMessageHandler{
    public function __invoke(ProcessEmailMessage $message): void
    {
        // do something with your message
    }
}
