<?php
// =============================================================
// KYZ Logística – Escritor XLSX mínimo (PHP puro, sin librerías)
// Genera un libro con una sola hoja a partir de filas (array de arrays).
// =============================================================

class XlsxWriter
{
    /**
     * @param array<int,array<int,string|int|float|null>> $rows
     */
    public function toString(array $rows, string $sheetName = 'Domicilios'): string
    {
        $sheetName = $this->sanitizeSheetName($sheetName);
        $sheetXml = $this->buildSheetXml($rows);

        $zip = new ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'kyz_xlsx_');
        if ($tmp === false) {
            throw new RuntimeException('No se pudo crear archivo temporal.');
        }
        @unlink($tmp);

        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el ZIP XLSX.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->relsRoot());
        $zip->addFromString('xl/workbook.xml', $this->workbook($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $bin = file_get_contents($tmp);
        @unlink($tmp);
        if ($bin === false) {
            throw new RuntimeException('No se pudo leer el XLSX generado.');
        }
        return $bin;
    }

    private function sanitizeSheetName(string $name): string
    {
        $name = preg_replace('/[\[\]\*\/\\\?:]/', '', $name) ?? $name;
        $name = trim(mb_substr($name, 0, 31));
        return $name !== '' ? $name : 'Hoja1';
    }

    /**
     * @param array<int,array<int,string|int|float|null>> $rows
     */
    private function buildSheetXml(array $rows): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">',
            '<sheetData>',
        ];

        foreach ($rows as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $cells = [];
            foreach (array_values($row) as $cIdx => $value) {
                $ref = $this->cellRef($rIdx, $cIdx);
                if ($value === null || $value === '') {
                    $cells[] = '<c r="' . $ref . '"/>';
                    continue;
                }
                if (is_int($value) || is_float($value)) {
                    $cells[] = '<c r="' . $ref . '"><v>' . $this->xmlEscape((string)$value) . '</v></c>';
                } else {
                    $t = $this->xmlEscape((string)$value);
                    $cells[] = '<c r="' . $ref . '" t="inlineStr"><is><t>' . $t . '</t></is></c>';
                }
            }
            $lines[] = '<row r="' . $rowNum . '">' . implode('', $cells) . '</row>';
        }

        $lines[] = '</sheetData>';
        $lines[] = '</worksheet>';
        return implode('', $lines);
    }

    private function cellRef(int $rowIndex, int $colIndex): string
    {
        return $this->columnName($colIndex) . ($rowIndex + 1);
    }

    private function columnName(int $col): string
    {
        $name = '';
        $n = $col;
        while ($n >= 0) {
            $name = chr(65 + ($n % 26)) . $name;
            $n = intdiv($n, 26) - 1;
        }
        return $name;
    }

    private function xmlEscape(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function relsRoot(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->xmlEscape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '</styleSheet>';
    }
}
