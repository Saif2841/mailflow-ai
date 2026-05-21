<?php

namespace App\Controller;

use App\Service\Supabase\SupabaseRestClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/inbox', name: 'legacy_inbox')]
    public function index(Request $request, SupabaseRestClient $supabase): Response
    {
        $status = $request->query->get('status');
        $query = $request->query->get('q');
        $hasAttachments = $request->query->get('has_attachments');
        $needsHumanReview = $request->query->get('needs_human_review');

        $filters = [];
        if (is_string($status) && $status !== '') {
            $filters['processing_status'] = $status;
        }

        if (is_string($query) && $query !== '') {
            $filters['subject'] = ['op' => 'ilike', 'value' => '%'.$query.'%'];
        }

        if ($hasAttachments === 'true') {
            $filters['has_attachments'] = true;
        } elseif ($hasAttachments === 'false') {
            $filters['has_attachments'] = false;
        }

        $emails = $supabase->select('emails', $filters, [
            'order' => ['column' => 'received_at', 'ascending' => false],
        ]);

        $orders = $supabase->select('orders', [], [
            'order' => ['column' => 'order_date', 'ascending' => false],
            'limit' => 25,
        ]);

        $emailIds = array_values(array_filter(array_map(static fn ($email) => $email['id'] ?? null, $emails)));
        $attachments = [];
        $classifications = [];
        $routingDecisions = [];

        if (count($emailIds) > 0) {
            $attachmentRows = $supabase->select('email_attachments', [
                'email_id' => ['op' => 'in', 'value' => $emailIds],
            ], [
                'order' => ['column' => 'created_at', 'ascending' => true],
            ]);

            foreach ($attachmentRows as $attachment) {
                $emailId = $attachment['email_id'] ?? null;
                if ($emailId === null) {
                    continue;
                }

                $attachments[$emailId][] = $attachment;
            }

            $classificationRows = $supabase->select('ai_classifications', [
                'email_id' => ['op' => 'in', 'value' => $emailIds],
            ], [
                'order' => ['column' => 'classified_at', 'ascending' => false],
            ]);

            foreach ($classificationRows as $classification) {
                $emailId = $classification['email_id'] ?? null;
                if ($emailId === null || isset($classifications[$emailId])) {
                    continue;
                }

                $classifications[$emailId] = $classification;
            }

            $auditRows = $supabase->select('audit_logs', [
                'entity_type' => 'email',
                'action' => 'workflow_routed',
                'entity_id' => ['op' => 'in', 'value' => $emailIds],
            ], [
                'order' => ['column' => 'created_at', 'ascending' => false],
            ]);

            foreach ($auditRows as $auditRow) {
                $emailId = $auditRow['entity_id'] ?? null;
                if ($emailId === null || isset($routingDecisions[$emailId])) {
                    continue;
                }

                $routingDecisions[$emailId] = $this->extractRoutingDecision($auditRow['data'] ?? null);
            }
        }

        if ($needsHumanReview === 'true' || $needsHumanReview === 'false') {
            $target = $needsHumanReview === 'true';
            $emails = array_values(array_filter($emails, static function (array $email) use ($classifications, $target): bool {
                $emailId = $email['id'] ?? null;
                $classification = $classifications[$emailId] ?? null;
                if (!is_array($classification)) {
                    return false;
                }

                $requiresHumanReview = (bool) ($classification['requires_human_review'] ?? false);

                return $requiresHumanReview === $target;
            }));
        }

        return $this->render('dashboard/index.html.twig', [
            'emails' => $emails,
            'orders' => $orders,
            'attachments' => $attachments,
            'classifications' => $classifications,
            'routing_decisions' => $routingDecisions,
            'filters' => [
                'status' => $status ?? '',
                'q' => $query ?? '',
                'has_attachments' => $hasAttachments ?? '',
                'needs_human_review' => $needsHumanReview ?? '',
            ],
        ]);
    }

    private function extractRoutingDecision(mixed $data): ?string
    {
        if (!is_array($data)) {
            return null;
        }

        $decision = $data['decision'] ?? null;
        if (is_array($decision)) {
            $decision = $decision['decision'] ?? null;
        }

        if (!is_string($decision) || $decision === '') {
            return null;
        }

        return str_replace('_', ' ', $decision);
    }
}
