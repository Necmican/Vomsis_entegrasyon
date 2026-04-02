<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$txns = \App\Models\Transaction::selectRaw('DATE(transaction_date) as date, count(*) as count, sum(balance) as total_balance, avg(balance) as avg_balance')
        ->groupBy('date')
        ->orderBy('date', 'asc')
        ->get();

$out = "Tüm Tarihlerdeki İşlem Sayıları (BAKİYE İLE):\n";
foreach($txns as $t) {
    $out .= "Tarih: {$t->date} | Sayı: {$t->count} | Total/Avg Balance: {$t->total_balance} / {$t->avg_balance}\n";
}
file_put_contents('debug_balance.txt', $out);
echo "Bitti";
