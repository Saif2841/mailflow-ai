<?php

namespace App\Service;

use App\Service\Supabase\SupabaseRestClient;

final class PolicyEngineService
{
    private const CRITICAL_KEYWORDS = [
        'chargeback',
        'lawyer',
        'sue',
        'fraud',
        'bbb',
        'unacceptable',
    ];

    public function __construct(private SupabaseRestClient $supabase)
    {
    }

    public function evaluate(array $email, array $classification): array
    {
        $category = (string) ($classification['category'] ?? 'unknown');
        $body = strtolower((string) ($email['body_text'] ?? ''));
        $subject = strtolower((string) ($email['subject'] ?? ''));
        $content = $subject."\n".$body;

        $decision = 'route_to_support_workflow';
        $reason = 'Default routing.';
        $approverRole = null;
        $nextWorkflow = null;
        $urgency = 'medium';

        if ($category === 'vendor_invoice') {
            $amount = $this->extractAmount($content);
            if ($amount > 5000) {
                $approverRole = 'finance_director';
            } elseif ($amount >= 500) {
                $approverRole = 'department_head';
            } else {
                $approverRole = 'project_manager';
            }

            $invoiceNumber = $this->extractInvoiceNumber($content);
            if ($invoiceNumber !== null) {
                $duplicate = $this->supabase->select('invoice_records', [
                    'invoice_number' => $invoiceNumber,
                ]);
                if (count($duplicate) > 0) {
                    $decision = 'flag_duplicate';
                    $reason = 'Duplicate invoice number detected.';
                }
            }

            if ($decision !== 'flag_duplicate') {
                $decision = 'route_to_invoice_workflow';
                $reason = 'Vendor invoice detected.';
            }
        }

        if (in_array($category, ['refund_request', 'customer_complaint', 'return_request'], true)) {
            $decision = 'route_to_support_workflow';
            $reason = 'Customer support workflow.';

            if ($this->containsCriticalKeyword($content)) {
                $decision = 'escalate_to_human';
                $reason = 'Critical keyword detected.';
                $urgency = 'critical';
            }

            if ($this->extractOrderNumber($content) === null) {
                $decision = 'request_more_info';
                $reason = 'Missing order number.';
                $nextWorkflow = 'request_order_number';
            }

            $amount = $this->extractAmount($content);
            if ($amount > 500) {
                $classification['requires_human_review'] = true;
            }
        }

        if (in_array($category, ['shipping_issue', 'order_status', 'product_question', 'customer_complaint'], true)) {
            $decision = 'route_to_support_workflow';
            $reason = 'Support category detected.';
        }

        if (in_array($category, ['payment_followup', 'approval_response'], true)) {
            $decision = 'route_to_invoice_workflow';
            $reason = 'Payment or approval category detected.';
        }

        if ($category === 'spam') {
            $decision = 'route_to_support_workflow';
            $reason = 'Spam detected.';
            $urgency = 'low';
        }

        return [
            'decision' => $decision,
            'reason' => $reason,
            'approver_role' => $approverRole,
            'next_workflow' => $nextWorkflow,
            'urgency' => $urgency,
        ];
    }

    private function containsCriticalKeyword(string $content): bool
    {
        foreach (self::CRITICAL_KEYWORDS as $keyword) {
            if (str_contains($content, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function extractInvoiceNumber(string $content): ?string
    {
        if (preg_match('/\bINV[-\s]?\d+\b/i', $content, $matches)) {
            return strtoupper($matches[0]);
        }

        return null;
    }

    private function extractOrderNumber(string $content): ?string
    {
        if (preg_match('/\bORD[-\s]?\d+\b/i', $content, $matches)) {
            return strtoupper($matches[0]);
        }

        return null;
    }

    private function extractAmount(string $content): float
    {
        $matches = [];
        preg_match_all('/(?:\$|usd\s*)?(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/i', $content, $matches);

        if (empty($matches[1])) {
            return 0.0;
        }

        $amounts = array_map(static function (string $raw): float {
            return (float) str_replace(',', '', $raw);
        }, $matches[1]);

        return max($amounts);
    }
}
