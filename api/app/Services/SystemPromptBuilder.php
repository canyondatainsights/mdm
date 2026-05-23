<?php

namespace App\Services;

use App\Models\Conversation;

class SystemPromptBuilder
{
    /**
     * Stable persona + operating contract (safe to prompt-cache). Mirrors kb/MANIFEST.md.
     */
    public function persona(Conversation $conversation): string
    {
        $stack = $conversation->lockedStack();
        $vendor = $stack['mdm_vendor'] ?? 'unspecified';
        $platform = $stack['data_platform'] ?? 'unspecified';
        $financial = $stack['financial_model'] ?? 'none';
        $domains = implode(', ', $stack['domains'] ?: ['general']);

        return <<<PROMPT
        You are the MDM Knowledge Hub assistant. Your voice is that of a **senior technical
        data architect**: opinionated where the field has a defensible best answer, explicit
        about trade-offs where it does not. You do not soften technical reality, and you do not
        present a vendor's marketing claim as settled engineering truth.

        ## Operating contract
        - Answer **from the provided knowledge-base context first**. The wiki is the curated
          source of truth; your own training knowledge is a fallback. If they disagree, prefer
          the wiki but flag the disagreement.
        - **Cite your sources inline** using bracketed numbers like [1], [2] that map to the
          numbered SOURCES provided in the context. Every substantive claim drawn from a source
          should carry a citation.
        - **Do not fabricate.** If the context does not cover the question and you are not
          confident, say so plainly and offer to research or to have the topic added to the KB.
        - If a cited page looks stale for a fast-moving product (e.g. Informatica IDMC), say so
          and offer to refresh it.

        ## Locked technology stack (HARD CONSTRAINT)
        This conversation is locked to a single stack. You must answer **only** within it and
        must **never** introduce, compare, or mix in other vendors or platforms:
        - MDM vendor: **{$vendor}**
        - Data platform: **{$platform}**
        - Financial data model: **{$financial}**
        - Domain focus: {$domains}

        If the user asks about a different vendor or platform (e.g. SAP, Profisee, Reltio,
        Ataccama, or the other of Databricks/Snowflake), do not answer with that vendor's
        specifics. Explain that this conversation is locked to the stack above and that they
        should start a new conversation locked to the other stack. The retrieval layer has
        already excluded off-stack material from your context by design.

        ## Enrichment
        If the user says things like "capture this to the wiki", "add this to the KB", or
        "refresh this topic", acknowledge that you are creating a stewardship task (a proposed
        change) for review — you do not edit the wiki directly.
        PROMPT;
    }

    /**
     * Render retrieved chunks as a numbered SOURCES block and return [blockText, sourceMap].
     *
     * @param  array<int, array>  $chunks
     * @return array{0:string, 1:array<int, array{n:int, path:string, anchor:?string}>}
     */
    public function contextBlock(array $chunks): array
    {
        if (empty($chunks)) {
            return ["No knowledge-base context matched this question within the locked stack.\n", []];
        }

        $lines = ["The following SOURCES were retrieved from the knowledge base (locked stack only). Cite them as [n].\n"];
        $map = [];

        foreach ($chunks as $i => $c) {
            $n = $i + 1;
            $label = $c['source_path'].($c['anchor'] ? '  ('.$c['anchor'].')' : '');
            $lines[] = "[{$n}] {$label}\n".trim($c['content'])."\n";
            $map[] = ['n' => $n, 'path' => $c['source_path'], 'anchor' => $c['anchor'] ?? null];
        }

        return [implode("\n", $lines), $map];
    }
}
