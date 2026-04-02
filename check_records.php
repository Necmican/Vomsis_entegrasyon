<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$txns = \App\Models\Transaction::selectRaw('DATE(transaction_date) as date, count(*) as count, sum(amount) as total')
        ->groupBy('date')
        ->orderBy('date', 'asc')
        ->get();

$out = "Tüm Tarihlerdeki İşlem Sayıları:\n";
foreach($txns as $t) {
    $out .= "Tarih: {$t->date} | Sayı: {$t->count} | Tutar: {$t->total}\n";
}
file_put_contents('debug_dates.txt', $out);
echo "Bitti";
