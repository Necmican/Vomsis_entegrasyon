<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$startDateStr = '2025-12-09';
$endDateStr   = '2026-02-09'; 
$selectedAccountId = null;

$accounts = \Illuminate\Support\Facades\DB::table('bank_accounts')->get(); 
$query = \App\Models\Transaction::whereBetween('transaction_date', [$startDateStr . ' 00:00:00', $endDateStr . ' 23:59:59'])
    ->orderBy('transaction_date', 'asc');

$transactions = $query->get();

$accountIds = $accounts->pluck('id')->toArray();

$accountBalances = [];
foreach ($accountIds as $accId) {
    $prevTxn = \App\Models\Transaction::where('bank_account_id', $accId)
        ->where('transaction_date', '<', $startDateStr . ' 00:00:00')
        ->orderBy('transaction_date', 'desc')
        ->first();
    $accountBalances[$accId] = $prevTxn ? $prevTxn->balance : 0;
}

$transactionsByDate = $transactions->groupBy(function($item) {
    return \Carbon\Carbon::parse($item->transaction_date)->format('Y-m-d');
});

$historyData = collect();
$startCarbon = \Carbon\Carbon::parse($startDateStr);
$endCarbon   = \Carbon\Carbon::parse($endDateStr);
$currentDate = $startCarbon->copy();

while ($currentDate->lte($endCarbon)) {
    $dateStr = $currentDate->format('Y-m-d');
    
    if ($transactionsByDate->has($dateStr)) {
        $txnsThatDay = $transactionsByDate->get($dateStr)->groupBy('bank_account_id');
        foreach ($txnsThatDay as $accId => $txns) {
            $accountBalances[$accId] = $txns->last()->balance;
        }
    }

    $dailyTotal = array_sum($accountBalances);
    $historyData->put($dateStr, $dailyTotal);
    $currentDate->addDay();
}

file_put_contents('debug_history_data.txt', json_encode($historyData, JSON_PRETTY_PRINT));
echo "Bitti";
