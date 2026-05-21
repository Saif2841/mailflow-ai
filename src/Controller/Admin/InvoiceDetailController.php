<?php

namespace App\Controller\Admin;

use App\Service\Supabase\SupabaseRestClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InvoiceDetailController extends AbstractController
{
    #[Route('/invoices/{id}', name: 'admin_invoice_detail')]
    public function show(string $id, SupabaseRestClient $supabase): Response
    {
        $invoice = $supabase->select('invoice_records', ['id' => $id])[0] ?? null;
        if (!$invoice) {
            throw $this->createNotFoundException('Invoice not found.');
        }

        $pdfPath = $invoice['stamped_file_storage_path'] ?? $invoice['original_file_storage_path'] ?? null;
        $pdfUrl = null;
        if ($pdfPath) {
            $pdfUrl = $supabase->createSignedUrl('invoices', $pdfPath, 3600);
        }

        $approvals = $supabase->select('approval_requests', [
            'invoice_id' => $id,
        ], [
            'order' => ['column' => 'created_at', 'ascending' => false],
        ]);

        $audit = $supabase->select('audit_logs', [
            'entity_type' => 'invoice',
            'entity_id' => $id,
        ], [
            'order' => ['column' => 'created_at', 'ascending' => false],
        ]);

        return $this->render('admin/invoice_detail.html.twig', [
            'invoice' => $invoice,
            'pdf_url' => $pdfUrl,
            'approvals' => $approvals,
            'audit_logs' => $audit,
        ]);
    }
}
