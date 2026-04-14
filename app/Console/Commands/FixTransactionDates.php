<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\BankAccount;
use App\Services\VomsisService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FixTransactionDates extends Command
{
    protected $signature = 'vomsis:fix-dates {--description : Try to fix using description as last resort}';
    protected $description = 'Fixes transactions with incorrect dates (especially 2026-04-03 anomaly)';

    public function handle()
    {
        $this->info("Starting Optimized Transaction Date Fix...");

        // 1. Identify problematic transactions
        $anomalyDate = '2026-04-03';
        $problematicCount = Transaction::whereDate('transaction_date', $anomalyDate)->count();

        if ($problematicCount === 0) {
            $this->info("No transactions found for $anomalyDate. Already fixed?");
            return;
        }

        $this->info("Found $problematicCount transactions on $anomalyDate to fix.");

        // 2. Fetch the broadly relevant range (2025-01-01 to 2026-04-06)
        // Fetches all accounts at once in 7-day chunks (Standard Vomsis v2 API pattern)
        $vomsis = new VomsisService();
        $start = Carbon::parse('2025-01-01');
        $end = Carbon::parse('2026-04-06');
        
        $this->info("Re-syncing broad range {$start->toDateString()} to {$end->toDateString()}...");

        $currentStart = $start->copy();
        $token = $vomsis->getToken();
        $totalMatches = 0;

        while ($currentStart->lessThanOrEqualTo($end)) {
            $currentEnd = $currentStart->copy()->addDays(6);
            if ($currentEnd->greaterThan($end)) $currentEnd = $end->copy();

            $this->comment("Fetching {$currentStart->format('d-m-Y')} - {$currentEnd->format('d-m-Y')} (All Accounts)...");

            try {
                $response = \Illuminate\Support\Facades\Http::withToken($token)->get(env('VOMSIS_API_URL') . "/transactions", [
                    'beginDate'  => $currentStart->format('d-m-Y'),
                    'endDate'    => $currentEnd->format('d-m-Y')
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $apiTxns = $data['transactions'] ?? $data['data'] ?? [];
                    $matchCount = 0;

                    foreach ($apiTxns as $apiTxn) {
                        // Crucial: Use 'vomsis_transaction_id' to find local record that is currently wrongly dated
                        $localTxn = Transaction::where('vomsis_transaction_id', $apiTxn['id'])
                            ->whereDate('transaction_date', $anomalyDate)
                            ->first();
                        
                        if ($localTxn) {
                            $correctDate = $apiTxn['date'] ?? $apiTxn['transaction_date'] ?? $apiTxn['system_date'] ?? null;
                            
                            if ($correctDate) {
                                $localTxn->update(['transaction_date' => $correctDate]);
                                $matchCount++;
                                $totalMatches++;
                            }
                        }
                    }
                    if ($matchCount > 0) {
                        $this->info(" --- Fixed $matchCount transactions in this chunk.");
                    }
                } else {
                    $this->error("API Error: " . $response->body());
                }
            } catch (\Exception $e) {
                $this->error("Exception in chunk: " . $e->getMessage());
            }

            $currentStart = $currentEnd->copy()->addDay();
            $this->info("Current Match Total: $totalMatches / $problematicCount");
            usleep(500000); // 0.5s sleep to respect rate limits
        }

        // 3. Last Resort: Fix via Description parsing
        if ($this->option('description')) {
            $this->info("Attempting last resort: Description parsing...");
            $stillProblematic = Transaction::whereDate('transaction_date', $anomalyDate)->get();
            foreach ($stillProblematic as $txn) {
                // Regex for dates like DD.MM.YYYY or DD/MM/YYYY
                if (preg_match('/(\d{2})[.\/](\d{2})[.\/](\d{4})/', $txn->description, $matches)) {
                    $parsedDate = "{$matches[3]}-{$matches[2]}-{$matches[1]} 12:00:00";
                    $txn->update(['transaction_date' => $parsedDate]);
                }
            }
        }

        $this->info("Fix complete. Running final count...");
        $remaining = Transaction::whereDate('transaction_date', $anomalyDate)->count();
        $this->info("Remaining problematic transactions: $remaining");
        
        if ($remaining > 0) {
            $this->warn("Note: $remaining transactions could not be fixed. They might be orphan records or missing from the API range.");
        }
    }
}
