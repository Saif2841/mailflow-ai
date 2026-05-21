<?php

namespace App\MessageHandler;

use App\Message\SupportWorkflowMessage;
use App\Service\EscalationService;
use App\Service\ReplyDrafterService;
use App\Service\Supabase\SupabaseRestClient;
use RuntimeException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SupportWorkflowHandler
{
    public function __construct(
        private SupabaseRestClient $supabase,
        private EscalationService $escalationService,
        private ReplyDrafterService $replyDrafterService,
        private MailerInterface $mailer
    ) {
    }

    public function __invoke(SupportWorkflowMessage $message): void
    {
        $emailRows = $this->supabase->select('emails', ['id' => $message->emailId]);
        $email = $emailRows[0] ?? null;

        if (!$email) {
            throw new RuntimeException('Email not found: '.$message->emailId);
        }

        $classification = $this->fetchClassification($message->emailId);
        $caseType = $this->mapCaseType($classification['category'] ?? 'other');
        $urgency = $classification['requires_human_review'] ?? false ? 'high' : 'medium';

        $orderNumber = $this->extractOrderNumber((string) ($email['body_text'] ?? ''));
        $order = $orderNumber ? $this->findOrder($orderNumber) : null;

        $casePayload = [
            'email_id' => $message->emailId,
            'order_id' => $order['id'] ?? null,
            'customer_email' => (string) ($email['sender_email'] ?? ''),
            'case_type' => $caseType,
            'urgency' => $urgency,
            'status' => $orderNumber ? 'open' : 'pending_reply',
        ];

        $caseRow = $this->supabase->insert('customer_cases', $casePayload);
        $caseId = $caseRow[0]['id'] ?? null;
        if ($caseId === null) {
            throw new RuntimeException('Failed to create customer case.');
        }

        $this->supabase->insert('case_events', [
            'case_id' => $caseId,
            'event_type' => 'case_created',
            'actor' => 'system',
        ]);

        if (!$orderNumber) {
            $this->handleMissingOrderNumber($caseId, $email);
            return;
        }

        $escalated = $this->escalationService->evaluateAndApply($caseId, $email, $order, $classification);
        if (!$escalated) {
            $this->replyDrafterService->draft($caseId, $email, $order, $caseType);
        }
    }

    private function fetchClassification(string $emailId): array
    {
        $rows = $this->supabase->select('ai_classifications', [
            'email_id' => $emailId,
        ], [
            'order' => ['column' => 'classified_at', 'ascending' => false],
            'limit' => 1,
        ]);

        return $rows[0] ?? [
            'category' => 'other',
            'confidence' => 0.0,
            'requires_human_review' => true,
        ];
    }

    private function extractOrderNumber(string $body): ?string
    {
        if (preg_match('/#(\d{4,})|order[:\s]+(\w+)/i', $body, $matches)) {
            return $matches[1] ?: $matches[2];
        }

        return null;
    }

    private function findOrder(string $orderNumber): ?array
    {
        $rows = $this->supabase->select('orders', [
            'order_number' => $orderNumber,
        ], [
            'limit' => 1,
        ]);

        return $rows[0] ?? null;
    }

    private function mapCaseType(string $category): string
    {
        return match ($category) {
            'refund_request' => 'refund',
            'return_request' => 'return',
            'shipping_issue' => 'shipping',
            'product_question' => 'product_question',
            'customer_complaint' => 'complaint',
            default => 'other',
        };
    }

    private function handleMissingOrderNumber(string $caseId, array $email): void
    {
        $firstName = $this->extractFirstName($email);
        $customerEmail = (string) ($email['sender_email'] ?? '');

        $template = (new TemplatedEmail())
            ->from('support@company.com')
            ->to($customerEmail)
            ->subject('Order number needed to continue')
            ->htmlTemplate('emails/request_order_number.html.twig')
            ->context([
                'first_name' => $firstName,
            ]);

        if ($customerEmail !== '') {
            $this->mailer->send($template);
        }

        $this->supabase->insert('case_events', [
            'case_id' => $caseId,
            'event_type' => 'info_requested',
            'actor' => 'system',
        ]);
    }

    private function extractFirstName(array $email): string
    {
        $name = (string) ($email['sender_name'] ?? '');
        if ($name !== '') {
            return ucfirst(strtolower(strtok($name, ' ')));
        }

        $emailAddress = (string) ($email['sender_email'] ?? '');
        if ($emailAddress !== '') {
            $local = explode('@', $emailAddress)[0] ?? '';
            return ucfirst(strtolower($local));
        }

        return 'there';
    }
}
