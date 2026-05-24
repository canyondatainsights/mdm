<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Services\Retrieval\Retriever;

/**
 * Resolves which industry/add-on extensions a conversation includes, from the user's chat message.
 *
 * Extensions are opt-in: a conversation includes core content by default and the assistant asks
 * which extensions to add. This turns the user's reply ("consent only", "include insurance", "no
 * extensions") into the conversation's `extensions` set BEFORE retrieval, so the next answer is
 * scoped accordingly. Deterministic keyword match — only runs when extensions are actually available.
 */
class ExtensionResolver
{
    /** extension slug => extra match phrases */
    private const SYNONYMS = [
        'insurance' => ['insurer'],
        'esg' => ['sustainability'],
        'fabric' => ['microsoft fabric'],
        'healthcare' => ['health care', 'health'],
    ];

    public function __construct(private Retriever $retriever) {}

    /**
     * @return array{available:array<int,string>, included:?array<int,string>}
     */
    public function resolve(Conversation $conversation, string $message): array
    {
        $available = $this->retriever->availableExtensions($conversation);
        if (! empty($available)) {
            $next = $this->fromMessage($conversation, $message, $available);
            if ($next !== null && $next !== ($conversation->extensions ?? null)) {
                $conversation->extensions = $next;
                $conversation->save();
            }
        }

        return ['available' => $available, 'included' => $conversation->extensions];
    }

    /** The new extensions set if the message explicitly changes it, else null (no change). */
    private function fromMessage(Conversation $conversation, string $message, array $available): ?array
    {
        $m = strtolower($message);

        if (preg_match('/\b(no extensions?|core only|just core|without extensions?|core c?\d?\d?0? only)\b/', $m)) {
            return [];
        }
        if (preg_match('/\b(all extensions?|include all|every extension)\b/', $m)) {
            return $available;
        }

        $hits = [];
        foreach ($available as $ext) {
            $needles = array_merge([strtolower($ext)], self::SYNONYMS[$ext] ?? []);
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($m, $needle)) {
                    $hits[] = $ext;
                    break;
                }
            }
        }
        if (! $hits) {
            return null;
        }

        // "only" replaces the set; otherwise add to whatever is already included.
        if (preg_match('/\bonly\b/', $m)) {
            return array_values(array_unique($hits));
        }

        return array_values(array_unique(array_merge($conversation->extensions ?? [], $hits)));
    }
}
