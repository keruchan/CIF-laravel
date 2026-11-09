<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class ReportWorkbook
{
    public static function cifReportXml(mixed $value): string
    {
        // XML 1.0 controls are invalid even inside escaped strings.
        return htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) ($value ?? '')) ?? '', ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function cifReportColumn(int $number): string
    {
        $out = '';
        for (; $number > 0; $number = intdiv($number - 1, 26)) {
            $out = chr(65 + ($number - 1) % 26).$out;
        }

        return $out;
    }

    public static function cifReportZip(array $files): string
    {
        $body = '';
        $directory = '';
        $count = 0;
        foreach ($files as $name => $content) {
            $offset = strlen($body);
            $crc = crc32($content);
            $compressed = function_exists('gzdeflate') ? gzdeflate($content, 6) : $content;
            $method = function_exists('gzdeflate') ? 8 : 0;
            if ($compressed === false) {
                throw new \RuntimeException('Could not prepare the workbook.');
            }
            $size = strlen($compressed);
            $length = strlen($content);
            $nameLength = strlen($name);
            $body .= pack('VvvvvvVVVvv', 0x04034B50, 20, 0, $method, 0, 33, $crc, $size, $length, $nameLength, 0).$name.$compressed;
            $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, 0, $method, 0, 33, $crc, $size, $length, $nameLength, 0, 0, 0, 0, 0, $offset).$name;
            $count++;
        }

        return $body.$directory.pack('VvvvvVVv', 0x06054B50, 0, 0, $count, $count, strlen($directory), strlen($body), 0);
    }

    public static function cifReportWorkbook(array $sheets): string
    {
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $rels = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $files = ['_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="'.$rels.'"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>'];
        $book = '<workbook xmlns="'.$ns.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $links = '<Relationships xmlns="'.$rels.'">';
        $types = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $defined = '';
        foreach ($sheets as $i => $sheet) {
            $id = $i + 1;
            $name = ReportWorkbook::cifReportXml($sheet['name']);
            $book .= '<sheet name="'.$name.'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
            $links .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
            $types .= '<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $last = ReportWorkbook::cifReportColumn(count($sheet['headers']));
            $end = count($sheet['rows']) + 5;
            $xml = '<worksheet xmlns="'.$ns.'"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:'.$last.$end.'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="28"/><cols>';
            foreach ($sheet['headers'] as $c => $header) {
                $xml .= '<col min="'.($c + 1).'" max="'.($c + 1).'" width="'.(str_contains($header, 'Email') ? 34 : (str_contains($header, 'Candidate') || $header === 'Position' ? 28 : 22)).'" customWidth="1"/>';
            }
            $xml .= '</cols><sheetData>';
            $rows = [['APEC HOMES | '.$sheet['name']], [$sheet['meta']], [$sheet['note']], ['Records: '.count($sheet['rows'])], $sheet['headers'], ...$sheet['rows']];
            foreach ($rows as $r => $values) {
                $n = $r + 1;
                $xml .= '<row r="'.$n.'"'.($n === 1 ? ' ht="34" customHeight="1"' : ($n === 2 || $n === 3 ? ' ht="32" customHeight="1"' : '')).'>';
                foreach ($values as $c => $value) {
                    $style = $n === 1 ? 1 : ($n < 5 ? 2 : ($n === 5 ? 3 : ($n % 2 ? 4 : 5)));
                    if ($n > 5 && is_float($value)) {
                        $style = $n % 2 ? 6 : 7;
                    }
                    $ref = ReportWorkbook::cifReportColumn($c + 1).$n;
                    // All text is an explicit string, including leading-zero IDs and formula-like input.
                    $xml .= '<c r="'.$ref.'" s="'.$style.'"';
                    if (is_int($value) || is_float($value)) {
                        $xml .= ' t="n"><v>'.$value.'</v></c>';
                    } else {
                        $xml .= ' t="inlineStr"><is><t xml:space="preserve">'.ReportWorkbook::cifReportXml($value).'</t></is></c>';
                    }
                }$xml .= '</row>';
            }
            $xml .= '</sheetData><autoFilter ref="A5:'.$last.max(5, $end).'"/><mergeCells count="4">';
            for ($r = 1; $r <= 4; $r++) {
                $xml .= '<mergeCell ref="A'.$r.':'.$last.$r.'"/>';
            }
            $xml .= '</mergeCells><printOptions horizontalCentered="1"/><pageMargins left="0.25" right="0.25" top="0.4" bottom="0.4" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/><headerFooter><oddFooter>&amp;LAPEC Homes&amp;RPage &amp;P of &amp;N</oddFooter></headerFooter></worksheet>';
            $files['xl/worksheets/sheet'.$id.'.xml'] = '<?xml version="1.0" encoding="UTF-8"?>'.$xml;
            $defined .= '<definedName name="_xlnm.Print_Titles" localSheetId="'.$i.'">'.ReportWorkbook::cifReportXml("'".str_replace("'", "''", $sheet['name'])."'!\$1:\$5").'</definedName>';
        }
        $files['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8"?>'.$book.'</sheets><definedNames>'.$defined.'</definedNames></workbook>';
        $files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8"?>'.$links.'<Relationship Id="styles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $files['[Content_Types].xml'] = '<?xml version="1.0" encoding="UTF-8"?>'.$types.'</Types>';
        $styles = '<styleSheet xmlns="'.$ns.'"><numFmts count="1"><numFmt numFmtId="164" formatCode="0.00"/></numFmts><fonts count="4"><font><sz val="11"/><name val="Calibri"/><color rgb="FF193458"/></font><font><b/><sz val="18"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font><font><sz val="10"/><name val="Calibri"/><color rgb="FF60738D"/></font><font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font></fonts><fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0B3085"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF008F98"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF1F6FC"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="8">';
        foreach ([[0, 0, 0], [1, 2, 0], [2, 0, 0], [3, 3, 0], [0, 0, 0], [0, 4, 0], [0, 0, 164], [0, 4, 164]] as [$font,$fill,$format]) {
            $styles .= '<xf numFmtId="'.$format.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="0" xfId="0" applyAlignment="1" applyNumberFormat="1"><alignment vertical="center" wrapText="1"/></xf>';
        }
        $files['xl/styles.xml'] = '<?xml version="1.0" encoding="UTF-8"?>'.$styles.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

        return ReportWorkbook::cifReportZip($files);
    }

    public static function cifReportPrint(array $sheets): string
    {
        $e = static fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>APEC Homes — Recruitment reports</title><style>@page{size:A4 landscape;margin:12mm}body{font:12px Arial,sans-serif;color:#193458;margin:24px}button{background:#0b3085;color:white;border:0;border-radius:8px;padding:12px 20px;cursor:pointer}section{margin:24px 0;break-before:page}section:first-of-type{break-before:auto}h1{background:#0b3085;color:white;padding:14px;font-size:20px}p{line-height:1.6;color:#60738d}table{border-collapse:collapse;width:100%;font-size:10px;table-layout:fixed}th,td{text-align:left;padding:7px 5px;overflow-wrap:anywhere;border-bottom:1px solid #dce6f2}th{background:#008f98;color:white}tr:nth-child(even){background:#f1f6fc}thead{display:table-header-group}tr{break-inside:avoid}@media print{body{margin:0}button{display:none}table{font-size:8px}section{margin:0}h1{print-color-adjust:exact}th{print-color-adjust:exact}}</style></head><body><button type="button" onclick="window.print()">Print / Save PDF</button>';
        foreach ($sheets as $sheet) {
            $html .= '<section><h1>APEC HOMES | '.$e($sheet['name']).'</h1><p>'.$e($sheet['meta']).'<br>'.$e($sheet['note']).'<br>Records: '.count($sheet['rows']).'</p><table><thead><tr>';
            foreach ($sheet['headers'] as $h) {
                $html .= '<th scope="col">'.$e($h).'</th>';
            }$html .= '</tr></thead><tbody>';
            foreach ($sheet['rows'] as $row) {
                $html .= '<tr>';
                foreach ($row as $value) {
                    $html .= '<td>'.$e($value).'</td>';
                }$html .= '</tr>';
            }
            if (! $sheet['rows']) {
                $html .= '<tr><td colspan="'.count($sheet['headers']).'">No records match these filters.</td></tr>';
            }$html .= '</tbody></table></section>';
        }

        return $html.'</body></html>';
    }
}
