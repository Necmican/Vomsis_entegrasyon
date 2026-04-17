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

            // TOPLAM SATIR SAYISINI HESAPLA (YÜZDE HESABI İÇİN)
            $totalRows = (clone $query)->count();
            $this->exportTask->update(['total_rows' => $totalRows]);

            $filename = 'Export_' . $this->exportTask->id . '_' . date('Ymd_His');

            // 1) EXCEL SÜRECİ (FROMQUERY İLE RAM ŞİŞMEDEN MİLYARLARCA SATIR İŞLENEBİLİR)
            if ($this->exportTask->type === 'excel') {
                $filename .= '.xlsx';
                $path = 'exports/' . $filename;
                
                Excel::store(
                    new TransactionsExport((clone $query), $params, $this->exportTask, $totalRows), 
                    $path,
                    'public'
                );

            } 
            // 2) PDF SÜRECİ (GÜVENLİK SINIRI: 1000 SATIR)
            else {
                $filename .= '.pdf';
                $path = 'exports/' . $filename;
                
                $transactions = (clone $query)->orderBy('transaction_date', 'desc')->take(1000)->get();
                
                // İlerleme yüzdesi PDF için çok hızlı gerçekleşeceğinden manuel başlatılır.
                $this->exportTask->update(['percentage' => 50]);

                $pdf = Pdf::loadView('exports.transactions', compact('transactions'));
                $pdf->setPaper('A4', 'landscape');
                
                Storage::disk('public')->put($path, $pdf->output());
                
                $this->exportTask->update(['percentage' => 100]);
            }

            // İşlem Tamamen Bitti
            $this->exportTask->update([
                'status' => 'completed',
                'file_path' => $path,
                'percentage' => 100
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
