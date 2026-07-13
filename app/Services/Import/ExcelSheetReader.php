<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ExcelSheetReader
{
    private ?Spreadsheet $spreadsheet = null;

    public function __construct(private readonly string $path) {}

    public function load(): Spreadsheet
    {
        if ($this->spreadsheet !== null) {
            return $this->spreadsheet;
        }

        $absolutePath = $this->resolvePath($this->path);

        if (! is_file($absolutePath)) {
            throw new RuntimeException("ملف Excel غير موجود: {$absolutePath}");
        }

        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $this->spreadsheet = $reader->load($absolutePath);

        return $this->spreadsheet;
    }

    public function sheet(string $name): Worksheet
    {
        $sheet = $this->load()->getSheetByName($name);

        if (! $sheet) {
            throw new RuntimeException("ورقة Excel غير موجودة: {$name}");
        }

        return $sheet;
    }

    /**
     * @return array<int, string>
     */
    public function uniqueColumnValues(string $sheetName, string $column, int $startRow = 2): array
    {
        $sheet = $this->sheet($sheetName);
        $values = [];

        for ($row = $startRow; $row <= $sheet->getHighestRow(); $row++) {
            $value = $this->cellValue($sheet, $column, $row);

            if ($value !== '') {
                $values[$value] = true;
            }
        }

        return array_keys($values);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function readAssociativeRows(string $sheetName, int $headerRow = 2, ?int $startRow = null, ?int $endRow = null): array
    {
        $sheet = $this->sheet($sheetName);
        $headers = $this->readHeaderRow($sheet, $headerRow);
        $start = $startRow ?? $headerRow + 1;
        $end = $endRow ?? $sheet->getHighestRow();
        $rows = [];

        for ($row = $start; $row <= $end; $row++) {
            $record = ['_row' => (string) $row];
            $hasValue = false;

            foreach ($headers as $column => $header) {
                $value = $this->cellValue($sheet, $column, $row);
                $record[$header] = $value;

                if ($value !== '') {
                    $hasValue = true;
                }
            }

            if ($hasValue) {
                $rows[] = $record;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, string> column letter => header label
     */
    public function readHeaderRow(Worksheet $sheet, int $headerRow): array
    {
        $headers = [];
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($index = 1; $index <= $highestColumnIndex; $index++) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
            $header = $this->cellValue($sheet, $column, $headerRow);

            if ($header !== '') {
                $headers[$column] = $header;
            }
        }

        return $headers;
    }

    public function cellValue(Worksheet $sheet, string $column, int $row): string
    {
        $value = $sheet->getCell($column . $row)->getValue();

        return trim((string) $value);
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            return $path;
        }

        return base_path($path);
    }
}
