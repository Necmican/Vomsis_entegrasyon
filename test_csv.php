<?php

$filePath = __DIR__.'/storage/app/hesap_hareketleri.csv';
$handle = fopen($filePath, "r");
$headerLine = fgets($handle);
$headerLine = str_replace("\xEF\xBB\xBF", '', $headerLine); 
$headers = str_getcsv($headerLine, ",");
$data = fgetcsv($handle, 10000, ",");
fclose($handle);

$combined = array_combine($headers, $data);
file_put_contents('debug_csv.json', json_encode($combined, JSON_PRETTY_PRINT));
