<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\StewardshipTask;
use App\Services\Retrieval\Retriever;
use App\Services\SettingsService;
use App\Services\SystemPromptBuilder;
use Generator;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Throwable;

class ChatService
{
    public function __construct(
        private Retriever $retriever,
        private SystemPromptBuilder $prompts,
        private SettingsService $settings,
    ) {}

    /**
     * Stream a reply. Yields SSE-ready arrays:
     *   ['type'=>'meta', ...] | ['type'=>'delta','text'=>..] | ['type'=>'done', ..] | ['type'=>'error',..]
     */
    public function stream(Conversation $conversation, string $userText): Generator
    {
        // 1. Persist the user turn.
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => ['text' => $userText],
        ]);

        // 2. Enrichment trigger -> stewardship task (we never edit the wiki directly).
        $enrichment = $this->maybeCreateStewardshipTask($conversation, $userText);

        // 3. Require a key.
        $key = $this->settings->anthropicKey();
        if (! $key) {
            yield ['type' => 'error', 'message' => 'No Claude API key configured. An administrator can set it in Settings.'];

            return;
        }

        // 4. Retrieve isolated context.
        $chunks = $this->retriever->retrieve($conversation, $userText);
        [$contextText, $sourceMap] = $this->prompts->contextBlock($chunks);

        yield ['type' => 'meta', 'sources_found' => count($sourceMap), 'enrichment' => $enrichment];

        $persona = $this->prompts->persona($conversation);
        $prompt = $contextText."\n\n---\nUser question:\n".$userText;

        // 5. Stream Claude.
        $full = '';
        try {
            $stream = Prism::text()
                ->using(Provider::Anthropic, config('mdm.anthropic.model'), ['api_key' => $key])
                ->withMaxTokens((int) config('mdm.anthropic.max_tokens'))
                ->withSystemPrompt($persona)
                ->withPrompt($prompt)
                ->asStream();

            foreach ($stream as $event) {
                if ($event instanceof TextDeltaEvent && $event->delta !== '') {
                    $full .= $event->delta;
                    yield ['type' => 'delta', 'text' => $event->delta];
                }
            }
        } catch (Throwable $e) {
            yield ['type' => 'error', 'message' => 'Generation failed: '.$e->getMessage()];

            return;
        }

        // 6. Resolve citations actually referenced in the answer, persist, finalize.
        $citations = $this->usedCitations($full, $sourceMap);
        $confidence = $this->confidence($sourceMap, $citations);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => [['type' => 'markdown', 'text' => $full]],
            'citations' => $citations,
            'confidence' => $confidence,
            'model' => config('mdm.anthropic.model'),
        ]);

        $conversation->touch();

        yield [
            'type' => 'done',
            'message_id' => $message->id,
            'citations' => $citations,
            'confidence' => $confidence,
        ];
    }

    private function maybeCreateStewardshipTask(Conversation $conversation, string $text): ?array
    {
        $lower = strtolower($text);
        foreach (config('mdm.enrichment_triggers', []) as $trigger) {
            if (str_contains($lower, $trigger)) {
                $task = StewardshipTask::create([
                    'type' => str_contains($lower, 'adr') ? 'adr' : 'wiki_edit',
                    'summary' => 'Enrichment requested in conversation #'.$conversation->id,
                    'proposed_content' => $text,
                    'status' => 'pending',
                    'proposed_by' => $conversation->user_id,
                    'conversation_id' => $conversation->id,
                ]);

                return ['task_id' => $task->id, 'status' => 'pending'];
            }
        }

        return null;
    }

    /** @return array<int, array{n:int, path:string, anchor:?string}> */
    private function usedCitations(string $text, array $sourceMap): array
    {
        if (empty($sourceMap)) {
            return [];
        }
        preg_match_all('/\[(\d+)\]/', $text, $m);
        $used = array_unique(array_map('intval', $m[1] ?? []));

        $cited = array_filter($sourceMap, fn ($s) => in_array($s['n'], $used, true));

        // If the model cited nothing explicitly, fall back to surfacing the retrieved set.
        return array_values($cited ?: $sourceMap);
    }

    private function confidence(array $sourceMap, array $citations): string
    {
        if (empty($sourceMap)) {
            return 'low';
        }

        return count($citations) >= 2 ? 'high' : 'medium';
    }
}
