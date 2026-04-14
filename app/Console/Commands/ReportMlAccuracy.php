<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\BankAccount;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ReportMlAccuracy extends Command
{
    protected $signature = 'vomsis:report-accuracy';
    protected $description = 'Report the accuracy score of the ML forecasting models';

    public function handle()
    {
        $this->info("AI Accuracy Report - Feedback Loop Analysis");
        $this->line("--------------------------------------------------");

        $currencies = ['TL', 'USD', 'EUR'];
        $batchPayload = ['accounts' => []];

        foreach ($currencies as $cur) {
            $accountIds = BankAccount::where('currency', $cur)
                ->where('is_visible', true)
                ->pluck('id')
                ->toArray();

            if (empty($accountIds)) continue;

            $transactions = Transaction::whereIn('bank_account_id', $accountIds)
                ->where('is_real', 1)
                ->where('description', 'NOT LIKE', '%virman%')
                ->where('description', 'NOT LIKE', '%VIRMAN%')
                ->where('description', 'NOT LIKE', '%VİRMAN%')
                ->orderBy('transaction_date', 'asc')
                ->get();

            if ($transactions->count() < 20) continue;

            $dates = [];
            $incomes = [];
            $expenses = [];
            
            // Group by date for a cleaner payload
            $daily = $transactions->groupBy(function($t) {
                return $t->transaction_date->format('Y-m-d');
            });

            foreach ($daily as $date => $txns) {
                $dates[] = $date;
                $incomes[] = (float)$txns->where('amount', '>', 0)->sum('amount');
                $expenses[] = (float)abs($txns->where('amount', '<', 0)->sum('amount'));
            }

            $batchPayload['accounts'][$cur] = [
                'dates' => $dates,
                'incomes' => $incomes,
                'expenses' => $expenses,
                'periods_to_predict' => 15,
                'period_type' => 'D'
            ];
        }

        if (empty($batchPayload['accounts'])) {
            $this->error("Not enough real data to perform backtesting.");
            return 1;
        }

        try {
            $this->comment("Contacting AI Engine for evaluation...");
            $response = Http::timeout(30)->post('http://python_ml:8000/api/forecast_batch', $batchPayload);

            if ($response->successful()) {
                $accuracies = $response->json('accuracies');
                
                if (!$accuracies) {
                    $this->error("AI Engine returned no accuracy data. Response: " . $response->body());
                    return 1;
                }

                $headers = ['Currency', 'Accuracy Score (%)', 'Status'];
                $data = [];

                foreach ($accuracies as $cur => $score) {
                    $status = '⚠️ Low';
                    if ($score >= 80) $status = '🎯 Excellent';
                    elseif ($score >= 50) $status = '⚖️ Moderate';

                    $data[] = [$cur, $score . '%', $status];
                }

                $this->table($headers, $data);
                
                $this->info("\nLearning Progress Note:");
                $this->line("The Accuracy Score is calculated by hiding the last 15 days from the AI, ");
                $this->line("letting it predict them, and comparing it with the actual bank data.");
                $this->line("As we fix more historical records (like the recent CSV fix), this score will trend upwards.");

            } else {
                $this->error("AI Engine error: " . $response->body());
            }
        } catch (\Exception $e) {
            $this->error("Connection error: " . $e->getMessage());
        }

        return 0;
    }
}
