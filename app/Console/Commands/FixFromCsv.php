<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FixFromCsv extends Command
{
    protected $signature = 'vomsis:fix-from-csv {--file=storage/app/hesap_hareketleri.csv}';
    protected $description = 'Fix transaction dates using a Vomsis CSV export file';

    public function handle()
    {
        $filePath = base_path($this->option('file'));

        if (!file_exists($filePath)) {
            $this->error("File not found: $filePath");
            return 1;
        }

        $this->info("Starting Final CSV Restoration from $filePath...");

        $file = fopen($filePath, 'r');
        $header = fgetcsv($file);
        $headerMap = array_flip($header);

        $idIdx = $headerMap['id'] ?? null;
        $vDateIdx = $headerMap['value_date'] ?? null;
        $tDateIdx = $headerMap['transaction_date'] ?? null;
        $sDateIdx = $headerMap['system_date'] ?? null;
        $descIdx = $headerMap['description'] ?? null;
        $amountIdx = $headerMap['amount'] ?? null;

        if ($idIdx === null) {
            $this->error("CSV missing 'id' column");
            return 1;
        }

        $processed = 0;
        $updated = 0;
        $notFoundInDb = 0;
        $noDateFound = 0;
        $anomalies = [];

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($file)) !== false) {
                $processed++;
                $vomsisId = $row[$idIdx];
                $amount = $row[$amountIdx] ?? 0;
                $description = $row[$descIdx] ?? '';

                // Identify the best available date
                $bestDate = null;
                $rawDates = [
                    $row[$vDateIdx] ?? '',
                    $row[$tDateIdx] ?? '',
                    $row[$sDateIdx] ?? ''
                ];

                foreach ($rawDates as $rd) {
                    if (!empty(trim($rd))) {
                        $bestDate = trim($rd);
                        break;
                    }
                }

                // If no date column has value, try to parse from description
                if (!$bestDate && !empty($description)) {
                    if (preg_match('/(\d{2})[\.\-\/](\d{2})[\.\-\/](\d{4})/', $description, $m)) {
                        $bestDate = $m[3] . '-' . $m[2] . '-' . $m[1];
                    }
                }

                if (!$bestDate) {
                    $noDateFound++;
                    continue;
                }

                // Only fix records currently on anomaly date 2026-04-03
                $txn = Transaction::where('vomsis_transaction_id', $vomsisId)
                    ->whereDate('transaction_date', '2026-04-03')
                    ->first();

                if ($txn) {
                    $correctDate = Carbon::parse($bestDate);
                    
                    // Check for extreme amounts
                    if (abs((float)$amount) > 10000000) {
                        $anomalies[] = [
                            'id' => $vomsisId,
                            'date' => $correctDate->toDateString(),
                            'amount' => $amount,
                            'desc' => $description
                        ];
                    }

                    $txn->update(['transaction_date' => $correctDate]);
                    $updated++;
                } else {
                    $notFoundInDb++;
                }

                if ($processed % 2000 === 0) {
                    $this->comment("Processed $processed rows... (Updated: $updated)");
                }
            }

            DB::commit();
            $this->info("Restoration Complete!");
            $this->info("Total Processed: $processed");
            $this->info("Updated in DB: $updated");
            $this->info("Not Found in DB: $notFoundInDb");
            $this->info("No Date Found in CSV: $noDateFound");

            if (!empty($anomalies)) {
                $this->warn("\n--- UNUSUAL TRANSACTIONS DETECTED (>10M TL) ---");
                foreach ($anomalies as $a) {
                    $this->line("ID: {$a['id']} | Date: {$a['date']} | Amount: {$a['amount']} | {$a['desc']}");
                }
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error: " . $e->getMessage());
        }

        fclose($file);
        return 0;
    }
}
