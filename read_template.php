<?php

require 'vendor/autoload.php';

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
$spreadsheet = $reader->load('modele_militaires (1).xlsx');
$sheet = $spreadsheet->getActiveSheet();

echo "=== EN-TÊTES (Ligne 1) ===" . PHP_EOL;
$headerRow = $sheet->getRowIterator(1, 1)->current();
$headers = [];
foreach ($headerRow->getCellIterator() as $cell) {
    $val = $cell->getValue();
    if ($val !== null) {
        $headers[] = $val;
    }
}
foreach ($headers as $i => $h) {
    echo ($i + 1) . ". " . $h . PHP_EOL;
}

echo PHP_EOL . "=== EXEMPLE (Ligne 2) ===" . PHP_EOL;
$dataRow = $sheet->getRowIterator(2, 2)->current();
$values = [];
foreach ($dataRow->getCellIterator() as $cell) {
    $values[] = $cell->getValue();
}
foreach ($values as $i => $v) {
    echo ($i + 1) . ". " . ($headers[$i] ?? "Col$i") . " => " . $v . PHP_EOL;
}
