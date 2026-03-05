<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Bank;
use App\Exports\TransactionsExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportController extends Controller
{
    private function getFilteredTransactions($request)
    {
        // Dashboard'daki kuryenin aynısı. Veritabanını filtreliyoruz.
        $query = Transaction::with(['bankAccount.bank', 'transactionType']);

        if ($request->filled('bank_id')) {
            $bank = Bank::find($request->bank_id);
            if ($bank) {
                $query = $bank->transactions()->with(['bankAccount.bank', 'transactionType']);
            }
        }
        
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('currency')) {
            $query->whereHas('bankAccount', function ($q) use ($request) {
                $q->where('currency', $request->currency);
            });
        }
        if ($request->filled('account_id')) {
            $query->where('bank_account_id', $request->account_id);
        }

        return $query->orderBy('transaction_date', 'desc')->get();
    }

    public function exportExcel(Request $request)
    {
        // 1. Filtrelenmiş veriyi çek
        $transactions = $this->getFilteredTransactions($request);

        // 2. Modaldan gelen checkbox değerlerini al (İşaretliyse true, değilse false döner)
        $ayriBankalar = $request->has('separate_banks');
        $ayriHesaplar = $request->has('separate_accounts');

        // 3. Yazdığımız Excel motorunu çalıştır ve indir
        return Excel::download(
            new TransactionsExport($transactions, $ayriBankalar, $ayriHesaplar), 
            'Hesap_Hareketleri_' . date('d_m_Y') . '.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        // 1. Veriyi çek
        $transactions = $this->getFilteredTransactions($request);

        // 2. PDF tasarımına veriyi yolla ve resmi çek
        $pdf = Pdf::loadView('exports.transactions', compact('transactions'));

        // PDF'in kağıt boyutunu A4 ve Yatay (Landscape) yap
        $pdf->setPaper('A4', 'landscape');

        // 3. İndir
        return $pdf->download('Hesap_Hareketleri_' . date('d_m_Y') . '.pdf');
    }
}