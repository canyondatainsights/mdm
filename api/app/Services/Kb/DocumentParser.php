<?php

namespace App\Services\Kb;

use Illuminate\Support\Facades\Process;
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
        // Primary: poppler pdftotext — fast and low-memory (avoids smalot OOM/gzuncompress fatals
        // on real-world PDFs, which can crash the queue worker).
        $text = $this->pdftotext($absPath);
        $pages = $this->pdfPageCount($absPath);

        // Last resort: the in-PHP parser (wrapped — it can be memory-hungry on complex PDFs).
        if ($text === null) {
            try {
                $pdf = (new PdfParser)->parseFile($absPath);
                $text = trim($pdf->getText());
                $pages = $pages ?: count($pdf->getPages());
            } catch (\Throwable $e) {
                $text = '';
            }
        }
        $text = (string) $text;
        $pages = (int) $pages;

        // Scanned/image PDFs carry little or no embedded text. Fall back to OCR.
        if ($this->looksScanned($text, $pages) && config('mdm.ocr.enabled')) {
            $ocr = $this->ocrPdf($absPath);
            if ($ocr !== null && strlen($ocr) > strlen($text)) {
                $text = $ocr;
            }
        }

        return [
            'body' => $text,
            'front_matter' => [],
            'title' => $this->basenameTitle($absPath),
            'doc_type' => 'PDF',
            'pages' => $pages ?: null,
        ];
    }

    /** Extract embedded text with poppler's pdftotext (UTF-8, to stdout). Null if unavailable. */
    private function pdftotext(string $absPath): ?string
    {
        try {
            $r = Process::timeout(180)->run([
                config('mdm.ocr.pdftotext', 'pdftotext'), '-q', '-enc', 'UTF-8', $absPath, '-',
            ]);
            if ($r->successful()) {
                return trim($r->output());
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return null;
    }

    /** Page count via poppler's pdfinfo. */
    private function pdfPageCount(string $absPath): ?int
    {
        try {
            $r = Process::timeout(30)->run([config('mdm.ocr.pdfinfo', 'pdfinfo'), $absPath]);
            if ($r->successful() && preg_match('/Pages:\s+(\d+)/', $r->output(), $m)) {
                return (int) $m[1];
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return null;
    }

    /** Heuristic: too little embedded text for the page count ⇒ likely a scanned image PDF. */
    private function looksScanned(string $text, int $pages): bool
    {
        $len = strlen($text);

        return $len < 100 || ($pages > 0 && $len < $pages * 80);
    }

    /** Rasterize the PDF (pdftoppm) and OCR each page (tesseract). Returns null on failure. */
    private function ocrPdf(string $absPath): ?string
    {
        $tesseract = config('mdm.ocr.tesseract', 'tesseract');
        $pdftoppm = config('mdm.ocr.pdftoppm', 'pdftoppm');
        $dpi = (int) config('mdm.ocr.dpi', 200);
        $maxPages = (int) config('mdm.ocr.max_pages', 80);

        $dir = sys_get_temp_dir().'/mdm-ocr-'.bin2hex(random_bytes(6));
        if (! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            return null;
        }

        try {
            // PDF pages -> PNGs (page-1.png, page-2.png, …), capped at max_pages.
            $r = Process::timeout(540)->run([
                $pdftoppm, '-png', '-r', (string) $dpi, '-l', (string) $maxPages, $absPath, $dir.'/page',
            ]);
            if (! $r->successful()) {
                return null;
            }

            $images = glob($dir.'/page*.png') ?: [];
            sort($images);
            if (empty($images)) {
                return null;
            }

            $out = [];
            foreach ($images as $img) {
                $t = Process::timeout(120)->run([$tesseract, $img, 'stdout']);
                if ($t->successful()) {
                    $out[] = trim($t->output());
                }
            }

            $joined = trim(implode("\n\n", array_filter($out)));

            return $joined !== '' ? $joined : null;
        } catch (\Throwable $e) {
            return null;
        } finally {
            // Best-effort cleanup of the temp images.
            foreach (glob($dir.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
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
