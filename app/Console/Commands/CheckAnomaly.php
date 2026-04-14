<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class CheckAnomaly extends Command {
    protected $signature = 'check:anomaly';
    public function handle() {
        $this->info("================ ANOMALY INVESTIGATION ================");
        $dailyStats = Transaction::select(
            DB::raw('DATE(transaction_date) as date'),
            DB::raw('COUNT(id) as count'),
            DB::raw('SUM(amount) as total_amount'),
            DB::raw('SUM(ABS(amount)) as total_abs_amount')
        )->groupBy('date')->orderBy('total_abs_amount', 'desc')->limit(5)->get();

        $this->info("Top 5 Days by Transaction Volume (Absolute Amount):");
        foreach($dailyStats as $stat) {
            $this->info("Date: {$stat->date} | Count: {$stat->count} | Total Amount: {$stat->total_amount} | Total Abs: {$stat->total_abs_amount}");
        }

        $this->info("\n--- March 30 Transactions Overview ---");
        $txns = Transaction::whereMonth('transaction_date', 3)->whereDay('transaction_date', 30)
            ->orderByRaw('ABS(amount) DESC')->limit(15)
            ->get(['id', 'vomsis_transaction_id', 'amount', 'balance', 'transaction_date', 'description']);

        foreach($txns as $t) {
            $this->info("ID: {$t->id} | Vomsis ID: {$t->vomsis_transaction_id} | Amount: {$t->amount} | Balance: {$t->balance} | Desc: {$t->description}");
        }

        $this->info("\n--- Exact Duplicates Check for March 30 ---");
        $duplicates = Transaction::whereMonth('transaction_date', 3)->whereDay('transaction_date', 30)
            ->select('amount', 'description', 'transaction_date', DB::raw('COUNT(*) as count'))
            ->groupBy('amount', 'description', 'transaction_date')
            ->having('count', '>', 5)
            ->get();

        foreach($duplicates as $dup) {
            $this->info("Amount: {$dup->amount} | Count: {$dup->count} | Date: {$dup->transaction_date} | Desc: {$dup->description}");
        }
        
        $this->info("Investigation Complete.");
    }
}
