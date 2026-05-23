<?php

namespace App\Services\Kb;

/**
 * Heading/paragraph-aware chunker. Splits on Markdown headings first, then packs
 * paragraphs up to a token budget with a small overlap. Each chunk records the
 * nearest heading as its anchor (used for citations like "§ 4.2 Trust Hierarchy").
 */
class Chunker
{
    private int $maxChars;
    private int $overlapChars;

    public function __construct()
    {
        // ~4 chars/token heuristic.
        $this->maxChars = ((int) config('mdm.chunking.max_tokens', 950)) * 4;
        $this->overlapChars = ((int) config('mdm.chunking.overlap_tokens', 130)) * 4;
    }

    /**
     * @return array<int, array{content:string, anchor:?string, token_count:int}>
     */
    public function chunk(string $body): array
    {
        $body = $this->normalize($body);
        $blocks = $this->splitIntoBlocks($body);

        $chunks = [];
        $buffer = '';
        $anchor = null;
        $bufferAnchor = null;

        $flush = function () use (&$chunks, &$buffer, &$bufferAnchor) {
            $text = trim($buffer);
            if ($text !== '') {
                $chunks[] = [
                    'content' => $text,
                    'anchor' => $bufferAnchor,
                    'token_count' => (int) ceil(mb_strlen($text) / 4),
                ];
            }
            $buffer = '';
        };

        foreach ($blocks as $block) {
            if ($block['type'] === 'heading') {
                $anchor = $block['text'];
                // Headings start fresh context; keep them attached to following content.
                if ($buffer !== '') {
                    $flush();
                    $bufferAnchor = $anchor;
                }
                $buffer .= ($buffer === '' ? '' : "\n\n").$block['text'];
                $bufferAnchor ??= $anchor;

                continue;
            }

            $bufferAnchor ??= $anchor;

            if (mb_strlen($buffer) + mb_strlen($block['text']) + 2 > $this->maxChars && trim($buffer) !== '') {
                $tail = $this->tail($buffer);
                $flush();
                $buffer = $tail;
                $bufferAnchor = $anchor;
            }

            $buffer .= ($buffer === '' ? '' : "\n\n").$block['text'];
        }

        $flush();

        return array_values(array_filter($chunks, fn ($c) => mb_strlen($c['content']) > 0));
    }

    private function splitIntoBlocks(string $body): array
    {
        $blocks = [];
        foreach (preg_split('/\n{2,}/', $body) as $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }
            if (preg_match('/^#{1,6}\s+(.+)$/', $para, $m)) {
                $blocks[] = ['type' => 'heading', 'text' => $para, 'anchor' => trim($m[1])];
            } else {
                $blocks[] = ['type' => 'para', 'text' => $para];
            }
        }

        return $blocks;
    }

    private function tail(string $text): string
    {
        if ($this->overlapChars <= 0 || mb_strlen($text) <= $this->overlapChars) {
            return '';
        }

        return mb_substr($text, -$this->overlapChars);
    }

    private function normalize(string $body): string
    {
        return str_replace(["\r\n", "\r"], "\n", $body);
    }
}
