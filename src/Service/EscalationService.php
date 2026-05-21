<?php

namespace App\Service;

use App\Service\Supabase\SupabaseRestClient;

final class EscalationService
{
    private const TRIGGERS_PATTERN = '/chargeback|lawyer|bbb|fraud|sue|worst|unacceptable|never again/i';

    public function __construct(private SupabaseRestClient $supabase)
    {
    }

    public function evaluateAndApply(string $caseId, array $email, ?array $order, array $classification): bool
    {
        $body = (string) ($email['body_text'] ?? '');
        $customerEmail = (string) ($email['sender_email'] ?? '');

        $triggered = false;
        $reasons = [];

        if (preg_match(self::TRIGGERS_PATTERN, $body)) {
            $triggered = true;
            $reasons[] = 'critical_keywords';
        }

        if ($order && isset($order['total_amount']) && (float) $order['total_amount'] > 500) {
            $triggered = true;
            $reasons[] = 'order_amount_gt_500';
        }

        if ($customerEmail !== '') {
            $openCases = $this->supabase->select('customer_cases', [
                'customer_email' => $customerEmail,
                'status' => ['op' => 'in', 'value' => ['open', 'pending_reply', 'escalated']],
            ]);

            if (count($openCases) > 2) {
                $triggered = true;
                $reasons[] = 'open_cases_gt_2';
            }
        }

        $confidence = isset($classification['confidence']) ? (float) $classification['confidence'] : 0.0;
        if ($confidence < 0.70) {
            $triggered = true;
            $reasons[] = 'low_ai_confidence';
        }

        if (!$triggered) {
            return false;
        }

        $this->supabase->update('customer_cases', $caseId, [
            'urgency' => 'critical',
            'assigned_to' => 'support_manager',
            'status' => 'escalated',
        ]);

        $this->supabase->insert('case_events', [
            'case_id' => $caseId,
            'event_type' => 'escalated',
            'actor' => 'system',
            'event_data' => [
                'reasons' => $reasons,
            ],
        ]);

        $this->supabase->insert('audit_logs', [
            'entity_type' => 'customer_case',
            'entity_id' => $caseId,
            'action' => 'escalated',
            'actor' => 'system',
            'data' => [
                'reasons' => $reasons,
                'email_id' => $email['id'] ?? null,
            ],
        ]);

        return true;
    }
}
