<?php

namespace App\Service;

use App\Service\Supabase\SupabaseRestClient;

final class ReplyDrafterService
{
    private const SYSTEM_PROMPT = "You are a helpful, empathetic customer support agent. Follow company policy:\n".
        "- Refunds accepted within 30 days for unopened items. Always process with a smile.\n".
        "- Replacements for damaged/wrong items. No questions asked.\n".
        "- Shipping delays: apologize and give tracking info.\n".
        "- Always start with an apology. Use the customer's first name.\n".
        "- Output ONLY the email reply body. No subject line. No JSON.";

    public function __construct(
        private OllamaService $ollama,
        private SupabaseRestClient $supabase
    ) {
    }

    public function draft(string $caseId, array $email, ?array $order, string $caseType): void
    {
        $body = (string) ($email['body_text'] ?? '');
        $orderJson = $order ? json_encode($order) : '{}';

        $prompt = sprintf(
            'Customer email: %s. Order data: %s. Case type: %s.',
            $body,
            $orderJson,
            $caseType
        );

        $reply = $this->ollama->generateDefault($prompt, self::SYSTEM_PROMPT);

        $this->supabase->update('customer_cases', $caseId, [
            'draft_reply' => $reply,
        ]);
    }
}
