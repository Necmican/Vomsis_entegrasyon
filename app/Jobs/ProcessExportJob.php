<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ExportTask;
use App\Models\Transaction;
use App\Models\Bank;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TransactionsExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes max execution for job
    public $tries = 2; // only try twice to not clog processing

    protected $exportTask;

    /**
     * Create a new job instance.
     */
    public function __construct(ExportTask $exportTask)
    {
        $this->exportTask = $exportTask;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->exportTask->update(['status' => 'processing']);

        try {
            // Reconstruct the parameters
            $params = $this->exportTask->params;
            
            // Build the query exactly as getFilteredTransactions did
            $query = Transaction::with(['bankAccount.bank', 'transactionType']);

            if (!empty($params['bank_id'])) {
                $bank = Bank::find($params['bank_id']);
                if ($bank) {
                    $query = $bank->transactions()->with(['bankAccount.bank', 'transactionType']);
                }
            }
            
            if (!empty($params['search'])) {
                $query->where('description', 'like', '%' . $params['search'] . '%');
            }
            if (!empty($params['currency'])) {
                $query->whereHas('bankAccount', function ($q) use ($params) {
                    $q->where('currency', $params['currency']);
                });
            }
            if (!empty($params['account_id'])) {
                $query->where('bank_account_id', $params['account_id']);
            }

            // Apply high limit to prevent memory crash but allow large export
            $transactions = $query->orderBy('transaction_date', 'desc')->limit(50000)->get();
            $this->exportTask->update(['total_rows' => $transactions->count()]);

            $filename = 'Export_' . $this->exportTask->id . '_' . date('Ymd_His');

            if ($this->exportTask->type === 'excel') {
                $filename .= '.xlsx';
                $path = 'exports/' . $filename;
                
                $ayriBankalar = !empty($params['separate_banks']);
                $ayriHesaplar = !empty($params['separate_accounts']);
                
                // Store on public disk (storage/app/public/exports)
                Excel::store(
                    new TransactionsExport($transactions, $ayriBankalar, $ayriHesaplar), 
                    $path,
                    'public'
                );

            } else {
                $filename .= '.pdf';
                $path = 'exports/' . $filename;
                
                $pdf = Pdf::loadView('exports.transactions', compact('transactions'));
                $pdf->setPaper('A4', 'landscape');
                
                // Saving raw PDF byte content
                Storage::disk('public')->put($path, $pdf->output());
            }

            // Mark as completed
            $this->exportTask->update([
                'status' => 'completed',
                'file_path' => $path
            ]);

        } catch (\Exception $e) {
            \Log::error("İhracat işlem hatası (ExportTask ID: {$this->exportTask->id}): " . $e->getMessage());
            $this->exportTask->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }
    }
}
