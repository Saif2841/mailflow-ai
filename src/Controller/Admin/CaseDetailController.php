<?php

namespace App\Controller\Admin;

use App\Service\Supabase\SupabaseRestClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

final class CaseDetailController extends AbstractController
{
    #[Route('/cases/{id}', name: 'admin_case_detail')]
    public function show(string $id, SupabaseRestClient $supabase): Response
    {
        $case = $supabase->select('customer_cases', ['id' => $id])[0] ?? null;
        if (!$case) {
            throw $this->createNotFoundException('Case not found.');
        }

        $email = $case['email_id'] ? ($supabase->select('emails', ['id' => $case['email_id']])[0] ?? null) : null;
        $events = $supabase->select('case_events', ['case_id' => $id], [
            'order' => ['column' => 'created_at', 'ascending' => false],
        ]);

        return $this->render('admin/case_detail.html.twig', [
            'case' => $case,
            'email' => $email,
            'events' => $events,
        ]);
    }

    #[Route('/cases/{id}/send-reply', name: 'case_send_reply', methods: ['POST'])]
    public function sendReply(string $id, Request $request, SupabaseRestClient $supabase, MailerInterface $mailer): Response
    {
        if (!$this->isCsrfTokenValid('case_reply_'.$id, (string) $request->request->get('_token'))) {
            return $this->redirect('/cases/'.$id);
        }

        $case = $supabase->select('customer_cases', ['id' => $id])[0] ?? null;
        if (!$case) {
            throw $this->createNotFoundException('Case not found.');
        }

        $reply = (string) $request->request->get('reply');
        $customerEmail = $case['customer_email'] ?? '';

        if ($customerEmail !== '') {
            $email = (new TemplatedEmail())
                ->from('support@company.com')
                ->to($customerEmail)
                ->subject('Support reply')
                ->html($reply);

            $mailer->send($email);
        }

        $supabase->update('customer_cases', $id, [
            'final_reply' => $reply,
            'sent_at' => (new \DateTimeImmutable())->format('c'),
            'status' => 'resolved',
        ]);

        $supabase->insert('case_events', [
            'case_id' => $id,
            'event_type' => 'reply_sent',
            'actor' => 'system',
        ]);

        $this->addFlash('success', 'Reply sent.');

        return $this->redirect('/cases/'.$id);
    }
}
