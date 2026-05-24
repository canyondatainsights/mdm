<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /** Tab/header colors (ARGB), cycled per sheet — mirrors the web design tokens. */
    private const PALETTE = ['FFE2603F', 'FF8B5CF6', 'FF14B8A6', 'FFF59E0B', 'FFE11D48', 'FF22C55E'];

    /** Header names (lowercased) that mark the per-entity grouping column, in priority order. */
    private const ENTITY_COLUMNS = ['target business entity', 'business entity', 'target entity', 'target field group'];

    /** Export the Markdown table(s) in an assistant message as a downloadable .xlsx. */
    public function xlsx(Request $request): StreamedResponse
    {
        $data = $request->validate(['message_id' => ['required', 'integer']]);

        $message = Message::with('conversation')->findOrFail($data['message_id']);

        // Owner of the conversation, or a Steward/Admin, may export.
        $user = $request->user();
        abort_unless(
            $message->conversation?->user_id === $user->id || $user->hasAnyRole(['Steward', 'Admin']),
            403,
        );

        $tables = $this->extractTables($this->messageText($message));
        abort_if(empty($tables), 422, 'This message has no table to export.');

        $spreadsheet = new Spreadsheet;
        $used = [];
        foreach ($this->buildSheetPlan($tables) as $i => $plan) {
            $sheet = $i === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($this->safeTitle($plan['title'], $used));
            $this->writeSheet($sheet, $plan['header'], $plan['rows'], self::PALETTE[$i % count(self::PALETTE)]);
        }
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $filename = 'mapping-'.$message->id.'.xlsx';

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Decide the workbook's sheets. A single S2T mapping table with a "Target Business Entity"
     * column (and ≥2 distinct entities) becomes an "All Mappings" overview tab plus one tab per
     * entity; otherwise each parsed table is its own sheet.
     *
     * @param  array<int, array{header: string[], rows: array<int, string[]>}>  $tables
     * @return array<int, array{title: string, header: string[], rows: array<int, string[]>}>
     */
    private function buildSheetPlan(array $tables): array
    {
        if (count($tables) === 1) {
            $t = $tables[0];
            $col = $this->entityColumnIndex($t['header']);
            if ($col !== null) {
                $groups = [];
                foreach ($t['rows'] as $row) {
                    $key = trim((string) ($row[$col] ?? '')) ?: 'Unspecified';
                    $groups[$key][] = $row;
                }
                if (count($groups) >= 2) {
                    $plan = [['title' => 'All Mappings', 'header' => $t['header'], 'rows' => $t['rows']]];
                    foreach ($groups as $entity => $rows) {
                        $plan[] = ['title' => $entity, 'header' => $t['header'], 'rows' => $rows];
                    }

                    return $plan;
                }
            }
        }

        $plan = [];
        foreach ($tables as $i => $t) {
            $plan[] = ['title' => $i === 0 ? 'Mapping' : 'Table '.($i + 1), 'header' => $t['header'], 'rows' => $t['rows']];
        }

        return $plan;
    }

    /** Index of the business-entity column in a header row, or null if none matches. */
    private function entityColumnIndex(array $header): ?int
    {
        foreach (self::ENTITY_COLUMNS as $name) {
            foreach ($header as $i => $h) {
                if (strtolower(trim((string) $h)) === $name) {
                    return $i;
                }
            }
        }

        return null;
    }

    /** Write a header + rows to a sheet with bold colored header, frozen pane, autosize, tab color. */
    private function writeSheet(Worksheet $sheet, array $header, array $rows, string $argb): void
    {
        foreach ($header as $c => $value) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($c + 1).'1', $value);
        }
        foreach ($rows as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c + 1).($r + 2), $value);
            }
        }

        $cols = max(1, count($header));
        $last = Coordinate::stringFromColumnIndex($cols);
        $headStyle = $sheet->getStyle("A1:{$last}1");
        $headStyle->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $headStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
        $sheet->freezePane('A2');
        for ($c = 1; $c <= $cols; $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }
        $sheet->getTabColor()->setARGB($argb);
    }

    /** Sheet-title-safe name: strip invalid chars, cap at 31, de-duplicate (case-insensitive). */
    private function safeTitle(string $name, array &$used): string
    {
        $clean = trim(preg_replace('/[*:\/\\\\?\[\]]/', '', $name) ?? '');
        $clean = $clean === '' ? 'Sheet' : mb_substr($clean, 0, 31);
        $base = $clean;
        $k = 2;
        while (in_array(mb_strtolower($clean), $used, true)) {
            $suffix = ' '.$k;
            $clean = mb_substr($base, 0, 31 - mb_strlen($suffix)).$suffix;
            $k++;
        }
        $used[] = mb_strtolower($clean);

        return $clean;
    }

    /** Flatten a stored message's content to plain markdown text. */
    private function messageText(Message $message): string
    {
        $content = $message->content;
        if (is_array($content) && isset($content['text'])) {
            return (string) $content['text'];
        }
        if (is_array($content)) {
            return collect($content)->map(fn ($b) => is_array($b) ? ($b['text'] ?? '') : (string) $b)->filter()->implode("\n\n");
        }

        return (string) $content;
    }

    /**
     * Extract GFM tables (header row + |---| separator + body rows) from markdown.
     *
     * @return array<int, array{header: string[], rows: array<int, string[]>}>
     */
    private function extractTables(string $text): array
    {
        $lines = preg_split('/\r\n|\n/', $text) ?: [];
        $strip = fn (string $c) => trim(str_replace(['**', '`', '__'], '', $c));
        $cells = fn (string $l) => array_map($strip, explode('|', trim(preg_replace('/^\s*\||\|\s*$/', '', $l) ?? $l)));
        $isSep = fn (?string $l) => $l !== null && str_contains($l, '|') && preg_match('/^[\s|:\-]*-[\s|:\-]*$/', $l) === 1;

        $tables = [];
        $n = count($lines);
        for ($i = 0; $i < $n; $i++) {
            if (str_contains($lines[$i], '|') && $isSep($lines[$i + 1] ?? null)) {
                $header = $cells($lines[$i]);
                $rows = [];
                $i += 2;
                while ($i < $n && str_contains($lines[$i], '|') && trim($lines[$i]) !== '') {
                    $rows[] = $cells($lines[$i]);
                    $i++;
                }
                $tables[] = ['header' => $header, 'rows' => $rows];
            }
        }

        return $tables;
    }
}
