<?php
require_once __DIR__ . '/../config/config.php';
require_login();

function exports_try_load_phpspreadsheet(): bool
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    return class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet');
}

function exports_send_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $r) {
        $line = [];
        foreach ($headers as $key) {
            $line[] = $r[$key] ?? '';
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

function exports_send_xlsx(string $filename, string $sheetName, array $headers, array $rows): void
{
    $ok = exports_try_load_phpspreadsheet();
    if (!$ok) {
        exports_send_csv(preg_replace('/\.xlsx$/', '.csv', $filename), $headers, $rows);
    }

    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($sheetName);

    $col = 1;
    foreach ($headers as $h) {
        $sheet->setCellValueByColumnAndRow($col, 1, $h);
        $col++;
    }

    $rowNum = 2;
    foreach ($rows as $r) {
        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueByColumnAndRow($col, $rowNum, (string)($r[$h] ?? ''));
            $col++;
        }
        $rowNum++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function exports_send_xlsx_multi(string $filename, array $sheets): void
{
    $ok = exports_try_load_phpspreadsheet();
    if (!$ok) {
        http_response_code(400);
        die('PhpSpreadsheet not installed. Use CSV exports or install via Composer.');
    }

    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);

    foreach ($sheets as $sheetDef) {
        $sheet = new PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, (string)$sheetDef['name']);
        $spreadsheet->addSheet($sheet);

        $headers = $sheetDef['headers'];
        $rows = $sheetDef['rows'];

        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueByColumnAndRow($col, 1, $h);
            $col++;
        }

        $rowNum = 2;
        foreach ($rows as $r) {
            $col = 1;
            foreach ($headers as $h) {
                $sheet->setCellValueByColumnAndRow($col, $rowNum, (string)($r[$h] ?? ''));
                $col++;
            }
            $rowNum++;
        }
    }

    $spreadsheet->setActiveSheetIndex(0);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
