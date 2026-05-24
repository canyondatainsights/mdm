<?php

namespace App\Services\Kb;

use App\Services\SettingsService;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

/**
 * Drafts wiki page content with Claude: given a title, the page's tags, and optional author
 * instructions, returns a GitHub-flavored Markdown body (no front-matter / no H1 — those are added by
 * WikiAuthor). The output is a draft for a steward to review and edit before saving.
 */
class WikiDrafter
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @param  array<string,?string>  $meta  mdm_vendor/data_platform/product/domain/financial_model
     * @param  ?string  $sourceText  when given (e.g. a fetched web page), the model rewrites/structures
     *                               it into a clean page instead of generating from scratch.
     */
    public function draft(string $title, array $meta = [], ?string $instructions = null, ?string $sourceText = null): string
    {
        $key = $this->settings->anthropicKey();
        if (! $key) {
            throw new \RuntimeException('No Claude API key configured (Admin → AI Settings).');
        }

        $ctx = array_filter([
            'MDM vendor' => $meta['mdm_vendor'] ?? null,
            'Data platform' => $meta['data_platform'] ?? null,
            'Product' => $meta['product'] ?? null,
            'Subject/domain' => $meta['domain'] ?? null,
            'Financial model' => $meta['financial_model'] ?? null,
        ]);
        $ctxLines = '';
        foreach ($ctx as $k => $v) {
            $ctxLines .= "  {$k}: {$v}\n";
        }

        $system = <<<'TXT'
        You are a senior Master Data Management (MDM) technical writer authoring an internal knowledge-base
        wiki page. Write clear, accurate, practitioner-grade content in GitHub-flavored Markdown.
        Rules:
        - Do NOT include YAML front-matter or a top-level "# H1" title — those are added automatically.
        - Open with a short intro paragraph, then organize with "## " section headings.
        - Use bullet lists and Markdown tables where they aid clarity.
        - For processes, flows, architectures, lifecycles, or relationships, include a diagram as a
          ```mermaid fenced code block (e.g. `flowchart TD`, `sequenceDiagram`) — these render as real
          diagrams. Prefer Mermaid over ASCII art. Keep diagram labels short.
        - Be specific to the given vendor/platform/subject, but do not invent product features you are
          unsure of; prefer durable concepts over volatile version-specific claims.
        - Keep it focused and skimmable. Output ONLY the Markdown body.
        TXT;

        $prompt = "Title: {$title}\n".($ctxLines ? "Context:\n{$ctxLines}" : '');
        if (filled($instructions)) {
            $prompt .= "\nAuthor instructions: {$instructions}\n";
        }
        if (filled($sourceText)) {
            $src = mb_substr(trim($sourceText), 0, 12000);
            $prompt .= "\nSOURCE CONTENT fetched from a web page — rewrite it into the wiki page, preserving the"
                ." facts and technical detail, trimming navigation/boilerplate, and not inventing anything:\n"
                ."\"\"\"\n{$src}\n\"\"\"\n\nRewrite the source above into the wiki page body now.";
        } else {
            $prompt .= "\nWrite the wiki page body now.";
        }

        $resp = Prism::text()
            ->using(Provider::Anthropic, $this->settings->anthropicModel(), ['api_key' => $key])
            ->withMaxTokens(filled($sourceText) ? 3000 : 2200)
            ->withSystemPrompt($system)
            ->withPrompt($prompt)
            ->asText();

        return trim($resp->text);
    }
}
