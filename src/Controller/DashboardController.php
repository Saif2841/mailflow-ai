<?php

namespace App\Controller;

use App\Service\Supabase\SupabaseRestClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(Request $request, SupabaseRestClient $supabase): Response
    {
        $status = $request->query->get('status');
        $query = $request->query->get('q');
        $hasAttachments = $request->query->get('has_attachments');

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

        $emailIds = array_values(array_filter(array_map(static fn ($email) => $email['id'] ?? null, $emails)));
        $attachments = [];

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
        }

        return $this->render('dashboard/index.html.twig', [
            'emails' => $emails,
            'attachments' => $attachments,
            'filters' => [
                'status' => $status ?? '',
                'q' => $query ?? '',
                'has_attachments' => $hasAttachments ?? '',
            ],
        ]);
    }
}
