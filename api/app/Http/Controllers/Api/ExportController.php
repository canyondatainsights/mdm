<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
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
        foreach ($tables as $i => $table) {
            $sheet = $i === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($i === 0 ? 'Mapping' : 'Table '.($i + 1));

            foreach ($table['header'] as $c => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c + 1).'1', $value);
            }
            foreach ($table['rows'] as $r => $row) {
                foreach ($row as $c => $value) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($c + 1).($r + 2), $value);
                }
            }

            $cols = max(1, count($table['header']));
            $last = Coordinate::stringFromColumnIndex($cols);
            $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);
            $sheet->freezePane('A2');
            for ($c = 1; $c <= $cols; $c++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
            }
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'mapping-'.$message->id.'.xlsx';

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
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
