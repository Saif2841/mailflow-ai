<?php

namespace App\Controller\Admin;

use App\Service\Supabase\SupabaseRestClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EmailsController extends AbstractController
{
    #[Route('/emails', name: 'admin_emails')]
    public function index(Request $request, SupabaseRestClient $supabase): Response
    {
        $category = $request->query->get('category');
        $status = $request->query->get('status');
        $startDate = $request->query->get('start');
        $endDate = $request->query->get('end');

        $filters = [];
        if (is_string($status) && $status !== '') {
            $filters['processing_status'] = $status;
        }
        if (is_string($startDate) && $startDate !== '') {
            $filters['received_at'] = ['op' => 'gte', 'value' => $startDate];
        }
        if (is_string($endDate) && $endDate !== '') {
            $filters['received_at'] = ['op' => 'lte', 'value' => $endDate];
        }

        $emails = $supabase->select('emails', $filters, [
            'order' => ['column' => 'received_at', 'ascending' => false],
            'limit' => 200,
        ]);

        $emailIds = array_values(array_filter(array_map(static fn ($email) => $email['id'] ?? null, $emails)));
        $classifications = [];
        $attachments = [];

        if (count($emailIds) > 0) {
            $classificationRows = $supabase->select('ai_classifications', [
                'email_id' => ['op' => 'in', 'value' => $emailIds],
            ], [
                'order' => ['column' => 'classified_at', 'ascending' => false],
            ]);

            foreach ($classificationRows as $row) {
                $emailId = $row['email_id'] ?? null;
                if ($emailId && !isset($classifications[$emailId])) {
                    $classifications[$emailId] = $row;
                }
            }

            $attachmentRows = $supabase->select('email_attachments', [
                'email_id' => ['op' => 'in', 'value' => $emailIds],
            ]);

            foreach ($attachmentRows as $row) {
                $emailId = $row['email_id'] ?? null;
                if ($emailId) {
                    $path = $row['supabase_storage_path'] ?? null;
                    if ($path) {
                        $row['signed_url'] = $supabase->createSignedUrl('invoices', $path, 3600);
                    }
                    $attachments[$emailId][] = $row;
                }
            }
        }

        if (is_string($category) && $category !== '') {
            $emails = array_values(array_filter($emails, static function (array $email) use ($classifications, $category): bool {
                $emailId = $email['id'] ?? null;
                $classification = $classifications[$emailId] ?? null;
                if (!is_array($classification)) {
                    return false;
                }

                return ($classification['category'] ?? '') === $category;
            }));
        }

        return $this->render('admin/emails.html.twig', [
            'emails' => $emails,
            'classifications' => $classifications,
            'attachments' => $attachments,
            'filters' => [
                'category' => $category ?? '',
                'status' => $status ?? '',
                'start' => $startDate ?? '',
                'end' => $endDate ?? '',
            ],
        ]);
    }
}
