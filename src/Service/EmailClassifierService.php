<?php

namespace App\Service;

use App\Service\Supabase\SupabaseRestClient;

final class EmailClassifierService
{
    private const CATEGORIES = [
        'vendor_invoice',
        'refund_request',
        'return_request',
        'shipping_issue',
        'order_status',
        'product_question',
        'customer_complaint',
        'payment_followup',
        'approval_response',
        'spam',
        'unknown',
    ];

    public function __construct(
        private OllamaService $ollama,
        private SupabaseRestClient $supabase
    ) {
    }

    public function classify(array $email, string $model = 'llama3.1:8b'): array
    {
        $subject = (string) ($email['subject'] ?? '');
        $bodyText = (string) ($email['body_text'] ?? '');
        $emailId = (string) ($email['id'] ?? '');

        $content = trim($subject."\n\n".$bodyText);
        if (strlen($content) > 2000) {
            $content = substr($content, 0, 2000);
        }

        $system = "You are an email classification AI for a company. Classify the email into exactly one category.\n".
            "Categories: vendor_invoice, refund_request, return_request, shipping_issue, order_status, product_question, customer_complaint, payment_followup, approval_response, spam, unknown.\n".
            "Return ONLY a valid JSON object with no explanation:\n".
            "{\"category\": \"...\", \"confidence\": 0.0, \"requires_human_review\": false, \"extracted_summary\": \"one sentence\"}";

        $result = $this->ollama->generateJson($model, $content, $system);

        $category = $result['category'] ?? 'unknown';
        if (!in_array($category, self::CATEGORIES, true)) {
            $category = 'unknown';
        }

        $confidence = is_numeric($result['confidence'] ?? null) ? (float) $result['confidence'] : 0.0;
        $requiresHumanReview = (bool) ($result['requires_human_review'] ?? false);
        if ($confidence < 0.75) {
            $requiresHumanReview = true;
        }

        $summary = isset($result['extracted_summary']) ? (string) $result['extracted_summary'] : null;

        $classification = [
            'email_id' => $emailId,
            'category' => $category,
            'confidence' => $confidence,
            'requires_human_review' => $requiresHumanReview,
            'extracted_summary' => $summary,
            'raw_response' => json_encode($result),
            'model_used' => $model,
        ];

        $this->supabase->insert('ai_classifications', $classification);
        $this->supabase->update('emails', $emailId, [
            'processing_status' => 'done',
        ]);

        return $classification;
    }
}
