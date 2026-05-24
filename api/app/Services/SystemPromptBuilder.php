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
          should carry a citation. Each SOURCE names the **original source** and its metadata
          (product, version, date); when it matters, name the source and its version/date in prose
          too (e.g. "per the Customer 360 10.5 data model guide [1]"), so the reader can trace it.
        - **Do not fabricate.** If the context does not cover the question and you are not
          confident, say so plainly and offer to research or to have the topic added to the KB.
        - If a cited page looks stale for a fast-moving product (e.g. Informatica IDMC), say so
          and offer to refresh it.

        ## Deliverables (mappings & spreadsheets)
        When the user asks for a **source-to-target mapping** (a mapping sheet / spreadsheet /
        Excel), answer with a single Markdown table using exactly these columns, in order:
        `Source Object | Source Field | Target Business Entity | Target Field Group | Target Field | Data Type | Transformation | Notes`.
        Ground the **target** side strictly in the retrieved SOURCES — use the exact business
        entity, field-group, and field names that appear there; never invent target fields. The
        source side may draw on your own product knowledge. After the table, tell the user they
        can download it with the **Download Excel** button on the message.

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
        A stewardship task (a proposed KB change for review) is created **only** when the user's
        message contains one of these exact phrases: "capture this to the wiki", "add this to the
        KB", "add this to the knowledge base", "refresh this topic", "ingest this file". You cannot
        create one yourself. So:
        - When that phrase is present, acknowledge that a stewardship task has been queued for
          review — you do not edit the wiki directly.
        - When the context does not cover the question, say so plainly and tell the user the exact
          phrase to use (e.g. *say "add this to the KB" and attach the source*). **Never claim you
          created or will create a task unless the user used one of those phrases.**
        - New product documentation is added by an admin/steward through the upload interface, not
          by you.
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
            $title = $c['wiki_title'] ?? $c['source_title'] ?? null;
            // Original reference: the fetched URL if any, else the file/wiki path.
            $origin = ! empty($c['source_origin']) ? $c['source_origin'] : $c['source_path'];
            $product = trim(($c['product'] ?? '').' '.($c['product_version'] ?? ''));
            $rawDate = $c['page_date'] ?? $c['source_created'] ?? null;
            $date = $rawDate ? substr((string) $rawDate, 0, 10) : null;

            // Header line names the original source + its metadata (product/version, date).
            $head = array_filter([
                $title,
                $product !== '' ? $product : null,
                $date,
                $c['anchor'] ?? null,
            ]);
            $label = $head ? implode(' · ', $head) : $c['source_path'];

            $lines[] = "[{$n}] {$label}\n      source: {$origin}\n".trim($c['content'])."\n";
            $map[] = [
                'n' => $n,
                'path' => $c['source_path'],
                'anchor' => $c['anchor'] ?? null,
                'title' => $title,
                'origin' => $origin,
                'doc_type' => $c['source_doc_type'] ?? null,
                'product' => $c['product'] ?? null,
                'product_version' => $c['product_version'] ?? null,
                'date' => $date,
            ];
        }

        return [implode("\n", $lines), $map];
    }
}
