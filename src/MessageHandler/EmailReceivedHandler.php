<?php

namespace App\MessageHandler;

use App\Message\EmailReceivedMessage;
use App\Service\EmailClassifierService;
use App\Service\PolicyEngineService;
use App\Service\Supabase\SupabaseRestClient;
use App\Service\WorkflowRouterService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class EmailReceivedHandler
{
    public function __construct(
        private SupabaseRestClient $supabase,
        private EmailClassifierService $classifier,
        private PolicyEngineService $policyEngine,
        private WorkflowRouterService $router,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(EmailReceivedMessage $message): void
    {
        $this->supabase->update('emails', $message->emailId, [
            'processing_status' => 'processing',
        ]);

        try {
            $emailRows = $this->supabase->select('emails', ['id' => $message->emailId]);
            $email = $emailRows[0] ?? null;

            if ($email === null) {
                $this->logger->warning('Email not found for classification.', [
                    'email_id' => $message->emailId,
                ]);
                return;
            }

            $classification = $this->classifier->classify($email);
            $decision = $this->policyEngine->evaluate($email, $classification);
            $this->router->route($email, $classification, $decision);
        } catch (\Throwable $exception) {
            $this->logger->error('Email processing failed.', [
                'email_id' => $message->emailId,
                'error' => $exception->getMessage(),
            ]);

            $this->supabase->update('emails', $message->emailId, [
                'processing_status' => 'failed',
            ]);

            throw $exception;
        }
    }
}
