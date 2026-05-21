<?php

namespace App\Controller\Admin;

use App\Service\Supabase\SupabaseRestClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuditLogController extends AbstractController
{
    #[Route('/audit-log', name: 'audit_log')]
    public function index(Request $request, SupabaseRestClient $supabase): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $logs = $supabase->select('audit_logs', [], [
            'order' => ['column' => 'created_at', 'ascending' => false],
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return $this->render('admin/audit_log.html.twig', [
            'logs' => $logs,
            'page' => $page,
        ]);
    }
}
