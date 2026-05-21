<?php

namespace App\Controller\Admin;

use App\Service\Supabase\SupabaseRestClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApprovalsController extends AbstractController
{
    #[Route('/approvals/{token}', name: 'public_approval', methods: ['GET'])]
    public function show(string $token, SupabaseRestClient $supabase): Response
    {
        $requestRow = $supabase->select('approval_requests', ['token' => $token])[0] ?? null;
        if (!$requestRow) {
            throw $this->createNotFoundException('Approval request not found.');
        }

        $invoice = $supabase->select('invoice_records', ['id' => $requestRow['invoice_id']])[0] ?? null;
        $pdfUrl = null;
        if ($invoice) {
            $path = $invoice['stamped_file_storage_path'] ?? $invoice['original_file_storage_path'] ?? null;
            if ($path) {
                $pdfUrl = $supabase->createSignedUrl('invoices', $path, 3600);
            }
        }

        return $this->render('admin/approval_public.html.twig', [
            'request' => $requestRow,
            'invoice' => $invoice,
            'pdf_url' => $pdfUrl,
        ]);
    }

    #[Route('/approvals/{token}/approve', name: 'approval_approve', methods: ['POST'])]
    public function approve(string $token, Request $request, SupabaseRestClient $supabase): Response
    {
        $requestRow = $supabase->select('approval_requests', ['token' => $token])[0] ?? null;
        if (!$requestRow) {
            throw $this->createNotFoundException('Approval request not found.');
        }

        $supabase->update('approval_requests', $requestRow['id'], [
            'status' => 'approved',
            'responded_at' => (new \DateTimeImmutable())->format('c'),
        ]);

        $supabase->update('invoice_records', $requestRow['invoice_id'], [
            'approval_status' => 'approved',
            'approved_at' => (new \DateTimeImmutable())->format('c'),
        ]);

        return $this->redirect('/approvals/'.$token);
    }

    #[Route('/approvals/{token}/reject', name: 'approval_reject', methods: ['POST'])]
    public function reject(string $token, Request $request, SupabaseRestClient $supabase): Response
    {
        $requestRow = $supabase->select('approval_requests', ['token' => $token])[0] ?? null;
        if (!$requestRow) {
            throw $this->createNotFoundException('Approval request not found.');
        }

        $supabase->update('approval_requests', $requestRow['id'], [
            'status' => 'rejected',
            'responded_at' => (new \DateTimeImmutable())->format('c'),
        ]);

        $supabase->update('invoice_records', $requestRow['invoice_id'], [
            'approval_status' => 'rejected',
        ]);

        return $this->redirect('/approvals/'.$token);
    }
}
