<?php

namespace App\Controller\Admin;

use App\Service\Supabase\SupabaseRestClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_dashboard')]
    public function index(SupabaseRestClient $supabase): Response
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $emailsToday = $supabase->count('emails', [
            'received_at' => ['op' => 'gte', 'value' => $today],
        ]);

        $pendingInvoices = $supabase->count('invoice_records', [
            'approval_status' => 'pending',
        ]);

        $openCases = $supabase->count('customer_cases', [
            'status' => 'open',
        ]);

        $awaitingApproval = $supabase->count('approval_requests', [
            'status' => 'pending',
        ]);

        $activity = $supabase->select('audit_logs', [], [
            'order' => ['column' => 'created_at', 'ascending' => false],
            'limit' => 20,
        ]);

        $cases = $supabase->select('customer_cases', [], [
            'order' => ['column' => 'created_at', 'ascending' => false],
            'limit' => 200,
        ]);

        $caseBreakdown = [];
        foreach ($cases as $case) {
            $type = $case['case_type'] ?? 'other';
            $caseBreakdown[$type] = ($caseBreakdown[$type] ?? 0) + 1;
        }

        return $this->render('admin/dashboard.html.twig', [
            'emails_today' => $emailsToday,
            'pending_invoices' => $pendingInvoices,
            'open_cases' => $openCases,
            'awaiting_approval' => $awaitingApproval,
            'activity' => $activity,
            'case_breakdown' => $caseBreakdown,
            'case_breakdown_labels' => array_keys($caseBreakdown),
            'case_breakdown_values' => array_values($caseBreakdown),
        ]);
    }
}
