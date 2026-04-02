<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$accountsForTotals = \App\Models\BankAccount::where('include_in_totals', true)->where('is_visible', true)->get();
$totals = [];
echo "DASHBOARD LOGIC:\n";
foreach ($accountsForTotals as $acc) {
    $latestTxn = \App\Models\Transaction::where('bank_account_id', $acc->id)
        ->orderBy('transaction_date', 'desc')
        ->first();
    $gercekBakiye = $latestTxn ? $latestTxn->balance : $acc->balance;
    echo "- Account " . $acc->id . " (" . $acc->currency . "): Latest Bal = " . $gercekBakiye . "\n";
    if (!isset($totals[$acc->currency])) $totals[$acc->currency] = 0;
    $totals[$acc->currency] += $gercekBakiye;
}
echo "Dashboard Totals:\n";
print_r($totals);

echo "\nANALYTICS LOGIC (Up to Feb 9):\n";
$startDateStr = '2025-12-09';
$endDateStr   = '2026-02-09'; 
$accountIds = $accountsForTotals->pluck('id')->toArray();
$accountBalances = [];
foreach ($accountIds as $accId) {
    $prevTxn = \App\Models\Transaction::where('bank_account_id', $accId)
        ->where('transaction_date', '<', $startDateStr . ' 00:00:00')
        ->orderBy('transaction_date', 'desc')
        ->first();
    $accountBalances[$accId] = $prevTxn ? $prevTxn->balance : 0;
}
$transactions = \App\Models\Transaction::whereBetween('transaction_date', [$startDateStr . ' 00:00:00', $endDateStr . ' 23:59:59'])
    ->whereIn('bank_account_id', $accountIds)
    ->orderBy('transaction_date', 'asc')
    ->get();
$txnsByDate = $transactions->groupBy(function($item) { return \Carbon\Carbon::parse($item->transaction_date)->format('Y-m-d'); });

$currDate = \Carbon\Carbon::parse($startDateStr);
$endC = \Carbon\Carbon::parse($endDateStr);
while ($currDate->lte($endC)) {
    $dateStr = $currDate->format('Y-m-d');
    if ($txnsByDate->has($dateStr)) {
        foreach ($txnsByDate->get($dateStr)->groupBy('bank_account_id') as $accId => $txns) {
            $accountBalances[$accId] = $txns->last()->balance;
        }
    }
    $currDate->addDay();
}
echo "Analytics Final Balances:\n";
print_r($accountBalances);
echo "Analytics Total: " . array_sum($accountBalances) . "\n";
