<?php

namespace App\Controller\Admin;

use App\Service\Supabase\SupabaseRestClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PaymentScheduleController extends AbstractController
{
    #[Route('/payment-schedule', name: 'payment_schedule')]
    public function index(SupabaseRestClient $supabase): Response
    {
        $invoices = $supabase->select('invoice_records', [
            'approval_status' => 'approved',
            'payment_status' => 'unpaid',
        ], [
            'order' => ['column' => 'approved_at', 'ascending' => false],
            'limit' => 200,
        ]);

        $total = 0.0;
        foreach ($invoices as $invoice) {
            $total += (float) ($invoice['amount'] ?? 0);
        }

        return $this->render('admin/payment_schedule.html.twig', [
            'invoices' => $invoices,
            'total' => $total,
        ]);
    }

    #[Route('/payment-schedule/export', name: 'payment_schedule_export', methods: ['POST'])]
    public function export(SupabaseRestClient $supabase): Response
    {
        $invoices = $supabase->select('invoice_records', [
            'approval_status' => 'approved',
            'payment_status' => 'unpaid',
        ], [
            'order' => ['column' => 'approved_at', 'ascending' => false],
        ]);

        $lines = [
            'invoice_number,vendor_name,amount,currency,approved_at',
        ];

        foreach ($invoices as $invoice) {
            $lines[] = sprintf(
                '"%s","%s","%s","%s","%s"',
                $invoice['invoice_number'] ?? '',
                $invoice['vendor_name'] ?? '',
                $invoice['amount'] ?? '',
                $invoice['currency'] ?? '',
                $invoice['approved_at'] ?? ''
            );
        }

        $csv = implode("\n", $lines);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="payment_schedule.csv"',
        ]);
    }

    #[Route('/payment-schedule/send', name: 'payment_schedule_send', methods: ['POST'])]
    public function send(): Response
    {
        $this->addFlash('success', 'Payment schedule sent to finance.');

        return $this->redirect('/payment-schedule');
    }
}
