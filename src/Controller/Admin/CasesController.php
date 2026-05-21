<?php

namespace App\Controller\Admin;

use App\Service\Supabase\SupabaseRestClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CasesController extends AbstractController
{
    #[Route('/cases', name: 'admin_cases')]
    public function index(SupabaseRestClient $supabase): Response
    {
        $cases = $supabase->select('customer_cases', [], [
            'order' => ['column' => 'created_at', 'ascending' => false],
            'limit' => 200,
        ]);

        return $this->render('admin/cases.html.twig', [
            'cases' => $cases,
        ]);
    }
}
