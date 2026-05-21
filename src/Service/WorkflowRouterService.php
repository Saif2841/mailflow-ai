<?php

namespace App\Service;

use App\Message\EscalateToHumanMessage;
use App\Message\InvoiceWorkflowMessage;
use App\Message\RequestMoreInfoMessage;
use App\Message\SupportWorkflowMessage;
use App\Service\Supabase\SupabaseRestClient;
use Symfony\Component\Messenger\MessageBusInterface;

final class WorkflowRouterService
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private SupabaseRestClient $supabase
    ) {
    }

    public function route(array $email, array $classification, array $decision): void
    {
        $emailId = (string) ($email['id'] ?? '');
        $decisionKey = (string) ($decision['decision'] ?? '');

        switch ($decisionKey) {
            case 'route_to_invoice_workflow':
                $this->messageBus->dispatch(new InvoiceWorkflowMessage($emailId, $decision));
                break;
            case 'route_to_support_workflow':
                $this->messageBus->dispatch(new SupportWorkflowMessage($emailId, $decision));
                break;
            case 'escalate_to_human':
                $this->messageBus->dispatch(new EscalateToHumanMessage($emailId, $decision));
                break;
            case 'request_more_info':
                $this->messageBus->dispatch(new RequestMoreInfoMessage($emailId, $decision));
                break;
            case 'flag_duplicate':
                $this->messageBus->dispatch(new EscalateToHumanMessage($emailId, $decision));
                break;
            default:
                $this->messageBus->dispatch(new SupportWorkflowMessage($emailId, $decision));
                break;
        }

        $this->supabase->insert('audit_logs', [
            'entity_type' => 'email',
            'entity_id' => $emailId,
            'action' => 'workflow_routed',
            'actor' => 'system',
            'data' => [
                'decision' => $decision,
                'category' => $classification['category'] ?? 'unknown',
            ],
        ]);
    }
}
