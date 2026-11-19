<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class XlsxExporter
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<list<int|float|string|null>>  $rows
     */
    public function download(string $filename, array $headings, iterable $rows): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'clims-report-');
        abort_if($path === false, 500, 'Unable to create the report file.');

        $archive = new ZipArchive;
        abort_unless($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, 'Unable to create the report archive.');

        $archive->addFromString('[Content_Types].xml', $this->contentTypes());
        $archive->addFromString('_rels/.rels', $this->rootRelationships());
        $archive->addFromString('xl/workbook.xml', $this->workbook());
        $archive->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $archive->addFromString('xl/styles.xml', $this->styles());
        $archive->addFromString('xl/worksheets/sheet1.xml', $this->worksheet($headings, $rows));
        $archive->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** @param list<string> $headings
     * @param  iterable<list<int|float|string|null>>  $rows
     */
    private function worksheet(array $headings, iterable $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $xml .= $this->row($headings, 1, true);
        $rowNumber = 2;
        foreach ($rows as $row) {
            $xml .= $this->row($row, $rowNumber++);
        }

        return $xml.'</sheetData></worksheet>';
    }

    /** @param list<int|float|string|null> $values */
    private function row(array $values, int $number, bool $heading = false): string
    {
        $cells = '';
        foreach ($values as $index => $value) {
            $reference = $this->column($index + 1).$number;
            if (is_int($value) || is_float($value)) {
                $cells .= '<c r="'.$reference.'"'.($heading ? ' s="1"' : '').'><v>'.$value.'</v></c>';
            } else {
                $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= '<c r="'.$reference.'" t="inlineStr"'.($heading ? ' s="1"' : '').'><is><t xml:space="preserve">'.$text.'</t></is></c>';
            }
        }

        return '<row r="'.$number.'">'.$cells.'</row>';
    }

    private function column(int $number): string
    {
        $column = '';
        while ($number > 0) {
            $number--;
            $column = chr(65 + ($number % 26)).$column;
            $number = intdiv($number, 26);
        }

        return $column;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="CLIMS Report" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>';
    }
}
