<?php
// =============================================================
// KYZ Logística – Lector XLSX nativo (PHP puro, sin librerías)
// Un archivo .xlsx es un ZIP con XML interno.
// Usa ZipArchive + SimpleXML, ambos incluidos en PHP.
// =============================================================

class XlsxReader
{
    private ZipArchive $zip;
    private array      $sharedStrings = [];
    private array      $sheets        = [];

    public function __construct(private string $filePath) {}

    // ── API pública ──────────────────────────────────────────

    /**
     * Retorna los nombres de todas las hojas.
     */
    public function getSheetNames(): array
    {
        $this->openZip();
        $this->loadWorkbook();
        return array_column($this->sheets, 'name');
    }

    /**
     * Retorna las filas de una hoja como array de arrays.
     * $sheetIndex: 0-based.  $skipRows: filas a saltar desde el inicio (ej: 1 para omitir headers).
     */
    public function getRows(int $sheetIndex = 0, int $skipRows = 0): array
    {
        $this->openZip();
        $this->loadSharedStrings();
        $this->loadWorkbook();

        if (!isset($this->sheets[$sheetIndex])) {
            return [];
        }

        $sheetPath = 'xl/' . $this->sheets[$sheetIndex]['path'];
        $xml = $this->readXml($sheetPath);
        if ($xml === null) {
            return [];
        }

        $rows   = [];
        $rowNum = 0;

        foreach ($xml->sheetData->row as $row) {
            if ($rowNum < $skipRows) {
                $rowNum++;
                continue;
            }

            $rowData = [];
            foreach ($row->c as $cell) {
                $colIndex = $this->colToIndex((string)$cell['r']);
                $value    = $this->getCellValue($cell);

                // Rellenar columnas vacías intermedias con null
                while (count($rowData) < $colIndex) {
                    $rowData[] = null;
                }
                $rowData[$colIndex] = $value;
            }

            $rows[] = $rowData;
            $rowNum++;
        }

        return $rows;
    }

    // ── Internos ─────────────────────────────────────────────

    private function openZip(): void
    {
        if (!isset($this->zip) || !($this->zip instanceof ZipArchive)) {
            $this->zip = new ZipArchive();
            if ($this->zip->open($this->filePath) !== true) {
                throw new RuntimeException("No se pudo abrir el archivo XLSX: {$this->filePath}");
            }
        }
    }

    private function loadSharedStrings(): void
    {
        if ($this->sharedStrings) {
            return;
        }
        $xml = $this->readXml('xl/sharedStrings.xml');
        if ($xml === null) {
            return;
        }
        foreach ($xml->si as $si) {
            // Puede ser <t> simple o varios <r><t>
            if (isset($si->t)) {
                $this->sharedStrings[] = (string)$si->t;
            } else {
                $text = '';
                foreach ($si->r as $r) {
                    $text .= (string)$r->t;
                }
                $this->sharedStrings[] = $text;
            }
        }
    }

    private function loadWorkbook(): void
    {
        if ($this->sheets) {
            return;
        }
        $xml = $this->readXml('xl/workbook.xml');
        if ($xml === null) {
            return;
        }

        // Leer relaciones de hojas
        $rels   = $this->readXml('xl/_rels/workbook.xml.rels');
        $relMap = [];
        if ($rels) {
            foreach ($rels->Relationship as $rel) {
                $relMap[(string)$rel['Id']] = (string)$rel['Target'];
            }
        }

        foreach ($xml->sheets->sheet as $sheet) {
            $rId  = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $path = $relMap[$rId] ?? null;
            $this->sheets[] = [
                'name' => (string)$sheet['name'],
                'path' => $path,
            ];
        }
    }

    private function readXml(string $path): ?SimpleXMLElement
    {
        $content = $this->zip->getFromName($path);
        if ($content === false) {
            return null;
        }
        return simplexml_load_string($content) ?: null;
    }

    private function getCellValue(SimpleXMLElement $cell): mixed
    {
        $type  = (string)($cell['t'] ?? '');
        $value = (string)$cell->v;

        if ($type === 's') {
            // Shared string
            return $this->sharedStrings[(int)$value] ?? '';
        }

        if ($type === 'b') {
            return (bool)(int)$value;
        }

        if ($value === '') {
            return null;
        }

        // Numérico o fecha
        return is_numeric($value) ? $value + 0 : $value;
    }

    /**
     * Convierte referencia de celda (ej: "C5") a índice de columna 0-based.
     */
    private function colToIndex(string $cellRef): int
    {
        preg_match('/^([A-Z]+)/', $cellRef, $m);
        $col   = $m[1] ?? 'A';
        $index = 0;
        foreach (str_split($col) as $char) {
            $index = $index * 26 + (ord($char) - ord('A') + 1);
        }
        return $index - 1;
    }

    public function __destruct()
    {
        if (isset($this->zip) && $this->zip instanceof ZipArchive) {
            $this->zip->close();
        }
    }
}
