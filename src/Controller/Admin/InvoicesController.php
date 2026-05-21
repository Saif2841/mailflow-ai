<?php

namespace App\Controller\Admin;

use App\Service\Supabase\SupabaseRestClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InvoicesController extends AbstractController
{
    #[Route('/invoices', name: 'admin_invoices')]
    public function index(SupabaseRestClient $supabase): Response
    {
        $invoices = $supabase->select('invoice_records', [], [
            'order' => ['column' => 'created_at', 'ascending' => false],
            'limit' => 200,
        ]);

        return $this->render('admin/invoices.html.twig', [
            'invoices' => $invoices,
        ]);
    }

    #[Route('/invoices/{id}/approve-action', name: 'invoice_approve', methods: ['POST'])]
    public function approve(string $id, Request $request, SupabaseRestClient $supabase): Response
    {
        $this->denyAccessUnlessGranted('ROLE_FINANCE');
        if (!$this->isCsrfTokenValid('invoice_action_'.$id, (string) $request->request->get('_token'))) {
            return $this->redirect('/invoices');
        }

        $supabase->update('invoice_records', $id, [
            'approval_status' => 'approved',
        ]);

        $this->addFlash('success', 'Invoice approved.');

        return $this->redirect('/invoices');
    }

    #[Route('/invoices/{id}/reject-action', name: 'invoice_reject', methods: ['POST'])]
    public function reject(string $id, Request $request, SupabaseRestClient $supabase): Response
    {
        $this->denyAccessUnlessGranted('ROLE_FINANCE');
        if (!$this->isCsrfTokenValid('invoice_action_'.$id, (string) $request->request->get('_token'))) {
            return $this->redirect('/invoices');
        }

        $supabase->update('invoice_records', $id, [
            'approval_status' => 'rejected',
        ]);

        $this->addFlash('warning', 'Invoice rejected.');

        return $this->redirect('/invoices');
    }

    #[Route('/invoices/export', name: 'invoice_export', methods: ['POST'])]
    public function export(Request $request, SupabaseRestClient $supabase): Response
    {
        $ids = $request->request->all('invoice_ids');
        if (!is_array($ids) || count($ids) === 0) {
            return $this->redirect('/invoices');
        }

        $rows = $supabase->select('invoice_records', [
            'id' => ['op' => 'in', 'value' => $ids],
        ]);

        $lines = [
            'invoice_number,vendor_name,amount,currency,invoice_date,due_date,project_name,expense_category',
        ];
        foreach ($rows as $row) {
            $lines[] = sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s"',
                $row['invoice_number'] ?? '',
                $row['vendor_name'] ?? '',
                $row['amount'] ?? '',
                $row['currency'] ?? '',
                $row['invoice_date'] ?? '',
                $row['due_date'] ?? '',
                $row['project_name'] ?? '',
                $row['expense_category'] ?? ''
            );
        }

        $csv = implode("\n", $lines);
        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="quickbooks_export.csv"',
        ]);
    }
}
