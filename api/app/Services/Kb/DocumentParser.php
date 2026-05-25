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
            // Example scripts / code (.sql, .py, .json, …) ingest as UTF-8 text, tagged by language.
            default => in_array($ext, config('mdm.uploads.extensions', []), true)
                ? $this->parseText($absPath, $this->codeType($ext))
                : null, // DOCX/XLSX/PPTX handled in a later phase
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
            // Prefer a real title pulled from the document's own text; fall back to the filename.
            'title' => $this->titleFromBody($text) ?? $this->basenameTitle($absPath),
            'doc_type' => 'PDF',
            'pages' => $pages ?: null,
        ];
    }

    /**
     * Best-effort document title from the first meaningful line of extracted text — skips page
     * numbers, dates, URLs, and running heads. Returns null when nothing usable is found (caller
     * falls back to the filename).
     */
    private function titleFromBody(string $body): ?string
    {
        $lines = array_values(array_filter(
            array_map(fn ($l) => trim((string) preg_replace('/\s+/', ' ', $l)), array_slice(preg_split('/\R/', $body) ?: [], 0, 25)),
            fn ($l) => $l !== '',
        ));
        $n = count($lines);
        if ($n === 0) {
            return null;
        }

        $isDate = fn (string $l): bool => (bool) preg_match('/^(jan(uary)?|feb(ruary)?|mar(ch)?|apr(il)?|may|jun(e)?|jul(y)?|aug(ust)?|sep(t(ember)?)?|oct(ober)?|nov(ember)?|dec(ember)?)\.?\s+\d{4}$/i', $l)
            || (bool) preg_match('/^(q[1-4]\s+)?\d{4}$/', $l);
        $isLegal = fn (string $l): bool => (bool) preg_match('/^(©|copyright|this software|u\.s\.\s|all rights reserved)/i', $l);
        // Noise: page numbers, URLs, bare versions, dates, legal, and lines too short/long to be a title.
        $isNoise = fn (string $l): bool => mb_strlen($l) < 3 || mb_strlen($l) > 120
            || ! preg_match('/\p{L}/u', $l)
            || (bool) preg_match('#^(page\s+\d+|\d+|v?\d+(\.\d+)+|https?://|www\.)#i', $l)
            || $isDate($l) || $isLegal($l);

        // Brand-mark (®/™) words → lowercase / original word arrays (mark stripped) for prefix matching.
        $wl = fn (string $s): array => preg_split('/\s+/', mb_strtolower(trim((string) preg_replace('/[®™©]/u', ' ', $s))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wo = fn (string $s): array => preg_split('/\s+/', trim((string) preg_replace('/[®™©]/u', ' ', $s)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Find a leading ®/™ brand banner (the per-doc-set product header, e.g. "Informatica® …").
        $bannerIdx = null;
        foreach (array_slice($lines, 0, 4) as $k => $l) {
            if (preg_match('/[®™]/u', $l)) {
                $bannerIdx = $k;
                break;
            }
        }

        if ($bannerIdx !== null) {
            // Candidate banner prefixes, incl. a banner that wraps onto the next line — but only when
            // that line is a SINGLE short word (e.g. "Cloud"), never a multi-word phrase (which would
            // be the title's first words, e.g. "Amazon S3 V2", and must not be eaten into the prefix).
            $prefixes = [$wl($lines[$bannerIdx])];
            $nextWords = isset($lines[$bannerIdx + 1]) ? $wl($lines[$bannerIdx + 1]) : [];
            if (count($nextWords) === 1 && mb_strlen($lines[$bannerIdx + 1]) <= 14 && ! $isDate($lines[$bannerIdx + 1])) {
                $prefixes[] = array_merge($wl($lines[$bannerIdx]), $nextWords);
            }

            // Primary signal: the doc's own "Product Title" line — a nearby line that begins with the
            // banner words and continues. That continuation IS the document title.
            $best = null;
            $bestLen = 0;
            for ($j = $bannerIdx + 1; $j < min($n, $bannerIdx + 8); $j++) {
                if ($isDate($lines[$j]) || $isLegal($lines[$j])) {
                    continue;
                }
                $lw = $wl($lines[$j]);
                $ow = $wo($lines[$j]);
                foreach ($prefixes as $pre) {
                    $pl = count($pre);
                    if ($pl > $bestLen && count($lw) > $pl && array_slice($lw, 0, $pl) === $pre) {
                        $suffix = trim(implode(' ', array_slice($ow, $pl)));
                        if (mb_strlen($suffix) >= 3) {
                            $best = $suffix;
                            $bestLen = $pl;
                        }
                    }
                }
            }
            if ($best !== null) {
                return $best;
            }

            // Fallback (clean cover with no combined line): skip the banner + dates, take the next line.
            $i = $bannerIdx + 1;
            while ($i < $n && $isNoise($lines[$i])) {
                $i++;
            }
            if ($i < $n) {
                $title = $lines[$i];
                if (mb_strlen($title) >= 18 && isset($lines[$i + 1]) && ! $isNoise($lines[$i + 1])
                    && mb_strlen($lines[$i + 1]) <= 25 && ! preg_match('/[.:!?]$/', $title)) {
                    $title .= ' '.$lines[$i + 1];
                }

                return $title;
            }
        }

        // Generic doc (no brand banner): the first content line that looks like a title (≥2 words).
        foreach ($lines as $l) {
            if (! $isNoise($l) && str_word_count((string) preg_replace('/[^\p{L} ]/u', ' ', $l)) >= 2) {
                return $l;
            }
        }

        return null;
    }

    /**
     * Fast, low-cost text excerpt (filename-independent, first pages only, NO OCR) for
     * pre-ingest classification. Keeps the classify endpoint responsive on large/scanned PDFs.
     */
    public function excerpt(string $absPath, int $maxChars = 6000): string
    {
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        $text = $ext === 'pdf'
            ? $this->pdftotextHead($absPath)
            : (in_array($ext, config('mdm.uploads.extensions', []), true) ? (string) file_get_contents($absPath) : '');

        return mb_substr(trim((string) $text), 0, $maxChars);
    }

    /** Re-derive a document title from its cover / first pages only — cheap, no full extraction. */
    public function coverTitle(string $absPath): ?string
    {
        if (strtolower(pathinfo($absPath, PATHINFO_EXTENSION)) === 'pdf') {
            $text = $this->pdftotextHead($absPath, 3);

            return $text ? $this->titleFromBody($text) : null;
        }

        return $this->parse($absPath)['title'] ?? null;
    }

    /** First few pages of embedded PDF text — cheap signal for classification. */
    private function pdftotextHead(string $absPath, int $pages = 3): ?string
    {
        try {
            $r = Process::timeout(60)->run([
                config('mdm.ocr.pdftotext', 'pdftotext'), '-q', '-f', '1', '-l', (string) $pages, '-enc', 'UTF-8', $absPath, '-',
            ]);
            if ($r->successful()) {
                return trim($r->output());
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return null;
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

    /** Display doc-type label for a code/script extension (e.g. yml → YAML, py → PY). */
    private function codeType(string $ext): string
    {
        return match ($ext) {
            'yml', 'yaml' => 'YAML',
            'markdown' => 'MD',
            default => strtoupper($ext),
        };
    }
}
