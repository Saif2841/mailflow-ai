<?php

namespace App\Controller;

use App\Message\EmailReceivedMessage;
use App\Service\Supabase\SupabaseRestClient;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class EmailIntakeController extends AbstractController
{
    public function __construct(
        private SupabaseRestClient $supabase,
        private MessageBusInterface $messageBus
    ) {
    }

    #[Route('/api/webhook/email-intake', name: 'api_webhook_email_intake', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            return $this->json(['success' => false, 'error' => 'Invalid JSON payload.'], 400);
        }

        $messageId = (string) ($payload['message_id'] ?? '');
        $senderEmail = (string) ($payload['sender_email'] ?? '');
        $receivedAt = (string) ($payload['received_at'] ?? '');

        if ($messageId === '' || $senderEmail === '' || $receivedAt === '') {
            return $this->json(['success' => false, 'error' => 'Missing required fields.'], 400);
        }

        $existing = $this->supabase->select('emails', ['message_id' => $messageId]);
        if (count($existing) > 0) {
            return $this->json(['success' => false, 'error' => 'Duplicate message_id.'], 409);
        }

        $attachments = is_array($payload['attachments'] ?? null) ? $payload['attachments'] : [];

        $emailRows = $this->supabase->insert('emails', [
            'message_id' => $messageId,
            'thread_id' => $payload['thread_id'] ?? null,
            'sender_email' => $senderEmail,
            'sender_name' => $payload['sender_name'] ?? null,
            'recipient_inbox' => $payload['recipient_inbox'] ?? null,
            'subject' => $payload['subject'] ?? null,
            'body_text' => $payload['body_text'] ?? null,
            'body_html' => $payload['body_html'] ?? null,
            'received_at' => $receivedAt,
            'has_attachments' => count($attachments) > 0,
            'processing_status' => 'pending',
        ]);

        $emailId = $emailRows[0]['id'] ?? null;
        if ($emailId === null) {
            return $this->json(['success' => false, 'error' => 'Failed to create email record.'], 500);
        }

        $receivedDate = $this->parseReceivedAt($receivedAt);
        $year = $receivedDate->format('Y');
        $month = $receivedDate->format('m');

        foreach ($attachments as $attachment) {
            $originalFilename = (string) ($attachment['filename'] ?? '');
            $base64 = (string) ($attachment['base64'] ?? '');
            $mimeType = (string) ($attachment['mime_type'] ?? 'application/octet-stream');

            if ($originalFilename === '' || $base64 === '') {
                continue;
            }

            $decoded = base64_decode($base64, true);
            if ($decoded === false) {
                return $this->json(['success' => false, 'error' => 'Invalid attachment encoding.'], 400);
            }

            $safeFilename = $this->sanitizeFilename($originalFilename);
            $storagePath = sprintf('emails/%s/%s/%s/%s', $year, $month, $emailId, $safeFilename);

            $this->supabase->uploadFile('invoices', $storagePath, $decoded, $mimeType);

            $this->supabase->insert('email_attachments', [
                'email_id' => $emailId,
                'filename' => $safeFilename,
                'original_filename' => $originalFilename,
                'supabase_storage_path' => $storagePath,
                'mime_type' => $mimeType,
                'file_size' => strlen($decoded),
            ]);
        }

        $this->supabase->insert('audit_logs', [
            'entity_type' => 'email',
            'entity_id' => $emailId,
            'action' => 'email_received',
            'actor' => 'system',
            'data' => [
                'message_id' => $messageId,
                'attachment_count' => count($attachments),
            ],
        ]);

        $this->messageBus->dispatch(new EmailReceivedMessage($emailId));

        return $this->json(['success' => true, 'email_id' => $emailId]);
    }

    private function parseReceivedAt(string $receivedAt): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($receivedAt);
        } catch (\Throwable $exception) {
            return new DateTimeImmutable();
        }
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);
        return trim($filename, '_');
    }
}
