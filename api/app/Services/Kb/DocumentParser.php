<?php

namespace App\Services\Kb;

use Smalot\PdfParser\Parser as PdfParser;
use Spatie\YamlFrontMatter\YamlFrontMatter;

/** Parses a KB file into front-matter, plain-text body, title, and doc type. */
class DocumentParser
{
    /**
     * @return array{body:string,front_matter:array,title:string,doc_type:string,pages:?int}|null
     */
    public function parse(string $absPath): ?array
    {
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));

        return match ($ext) {
            'md', 'markdown' => $this->parseMarkdown($absPath),
            'txt' => $this->parseText($absPath, 'TXT'),
            'pdf' => $this->parsePdf($absPath),
            default => null, // DOCX/XLSX/PPTX handled in a later phase
        };
    }

    private function parseMarkdown(string $absPath): array
    {
        $raw = file_get_contents($absPath);
        $doc = YamlFrontMatter::parse($raw);
        $body = $doc->body();

        return [
            'body' => $body,
            'front_matter' => $doc->matter(),
            'title' => $doc->matter('title') ?? $this->firstHeading($body) ?? $this->basenameTitle($absPath),
            'doc_type' => 'MD',
            'pages' => null,
        ];
    }

    private function parseText(string $absPath, string $type): array
    {
        return [
            'body' => file_get_contents($absPath),
            'front_matter' => [],
            'title' => $this->basenameTitle($absPath),
            'doc_type' => $type,
            'pages' => null,
        ];
    }

    private function parsePdf(string $absPath): array
    {
        $pdf = (new PdfParser)->parseFile($absPath);

        return [
            'body' => $pdf->getText(),
            'front_matter' => [],
            'title' => $this->basenameTitle($absPath),
            'doc_type' => 'PDF',
            'pages' => count($pdf->getPages()),
        ];
    }

    private function firstHeading(string $body): ?string
    {
        if (preg_match('/^#\s+(.+)$/m', $body, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function basenameTitle(string $absPath): string
    {
        return str(pathinfo($absPath, PATHINFO_FILENAME))->replace(['-', '_'], ' ')->title()->toString();
    }
}
