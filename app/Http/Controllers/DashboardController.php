<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use App\Models\TransactionType;
use App\Models\Bank;
use App\Models\Tag; 
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf; // PDF Motorunu içeri aktardık

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // 1. GENEL KASA TOPLAMLARI
        $totals = BankAccount::selectRaw('currency, SUM(balance) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');
        
        // 2. SOL MENÜ, FİLTRELER VE ETİKETLER
        $banks = Bank::with('bankAccounts')->orderBy('bank_name')->get(); 
        $transactionTypes = TransactionType::orderBy('name')->get(); 
        
        // Tüm etiketleri veritabanından çekip UI'a yolluyoruz
        $allTags = Tag::orderBy('name')->get(); 

        // 3. SEÇİLİ BANKA VE ANA SORGU
        $activeBank = null;
        $activeBankSummary = [];
        $activeBankAccounts = [];

        if ($request->filled('bank_id')) {
            $activeBank = Bank::with('bankAccounts')->find($request->bank_id);
            
            if ($activeBank) {
                // tags ilişkisini with() içine koyduk ki sistem hızlansın
                $query = $activeBank->transactions()->with(['bankAccount.bank', 'transactionType', 'tags']);
                $activeBankAccounts = $activeBank->bankAccounts;
                $activeBankSummary = $activeBank->bankAccounts->groupBy('currency')->map(function ($accounts) {
                    return [
                        'count' => $accounts->count(),
                        'total' => $accounts->sum('balance')
                    ];
                });
            } else {
                $query = Transaction::with(['bankAccount.bank', 'transactionType', 'tags']); 
            }
        } else {
            $query = Transaction::with(['bankAccount.bank', 'transactionType', 'tags']); 
        }

        // ========================================================================
        // 4. DİĞER DİNAMİK FİLTRELER
        // ========================================================================
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('currency')) {
            $query->whereHas('bankAccount', function ($q) use ($request) {
                $q->where('currency', $request->currency);
            });
        }
        if ($request->filled('type_code')) {
            $query->where('transaction_type_code', $request->type_code);
        }
        if ($request->filled('account_id')) {
            $query->where('bank_account_id', $request->account_id);
        }
        // ETİKETE GÖRE FİLTRELEME
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('tags.id', $request->tag_id);
            });
        }

        // ========================================================================
        // 4.5. DİNAMİK GELİR/GİDER HESABI
        // ========================================================================
        $summaryQuery = Transaction::join('bank_accounts', 'transactions.bank_account_id', '=', 'bank_accounts.id');

        if ($request->filled('bank_id')) {
            $summaryQuery->where('bank_accounts.bank_id', $request->bank_id);
        }
        if ($request->filled('search')) {
            $summaryQuery->where('transactions.description', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('currency')) {
            $summaryQuery->where('bank_accounts.currency', $request->currency);
        }
        if ($request->filled('type_code')) {
            $summaryQuery->where('transactions.transaction_type_code', $request->type_code);
        }
        if ($request->filled('account_id')) {
            $summaryQuery->where('transactions.bank_account_id', $request->account_id);
        }
        // ÖZET KISMI İÇİN ETİKET FİLTRELEMESİ
        if ($request->filled('tag_id')) {
            $summaryQuery->join('pos_transaction_tag', 'transactions.id', '=', 'pos_transaction_tag.pos_transaction_id')
                         ->where('pos_transaction_tag.tag_id', $request->tag_id);
        }

        $filteredSummaries = $summaryQuery
            ->selectRaw('bank_accounts.currency, 
                         SUM(CASE WHEN transactions.amount > 0 THEN transactions.amount ELSE 0 END) as total_income,
                         SUM(CASE WHEN transactions.amount < 0 THEN transactions.amount ELSE 0 END) as total_expense')
            ->groupBy('bank_accounts.currency')
            ->get();

        // 5. SAYFALAMA
        $transactions = $query->orderBy('transaction_date', 'desc')
                              ->paginate(20)
                              ->withQueryString();

        return view('dashboard', compact(
            'transactions', 'totals', 'transactionTypes', 'banks', 
            'filteredSummaries', 'activeBank', 'activeBankSummary', 'activeBankAccounts',
            'allTags' 
        ));
    }

    // ========================================================================
    // SAĞ TIK: GÖRÜNTÜLE, YAZDIR, PDF İNDİR VE E-POSTA İŞLEMLERİ
    // ========================================================================

    /**
     * Sağ tık ile faturayı web sayfasında (HTML) görüntüler
     */
    public function viewTransaction($id)
    {
        $transaction = Transaction::with(['bankAccount.bank', 'transactionType', 'tags'])->findOrFail($id);
        return view('pdf.transaction', compact('transaction'));
    }

    /**
     * Sağ tık ile faturayı açar ve tarayıcıya "YAZDIR" emri yollar
     */
    public function printTransaction($id)
    {
        $transaction = Transaction::with(['bankAccount.bank', 'transactionType', 'tags'])->findOrFail($id);
        return view('pdf.transaction', compact('transaction'))->with('is_print', true);
    }

    /**
     * Sağ tık ile işlemi PDF olarak bilgisayara indirir
     */
    public function downloadPdf($id)
    {
        $transaction = Transaction::with(['bankAccount.bank', 'transactionType', 'tags'])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.transaction', compact('transaction'));
        $pdf->setOptions(['defaultFont' => 'DejaVu Sans']);

        return $pdf->download('vomsis-islem-' . $transaction->id . '.pdf');
    }

    /**
     * Sağ tık ile PDF dekontu e-posta olarak gönderir
     */
    public function sendPdfEmail(Request $request, $id)
    {
        // 1. E-posta adresinin doğru girildiğinden emin ol
        $request->validate([
            'email' => 'required|email'
        ]);

        $transaction = Transaction::with(['bankAccount.bank', 'transactionType', 'tags'])->findOrFail($id);

        // 2. PDF'i bilgisayara indirmeden doğrudan bellekte (RAM) oluştur
        $pdf = Pdf::loadView('pdf.transaction', compact('transaction'));
        $pdf->setOptions(['defaultFont' => 'DejaVu Sans']);

        // 3. E-postayı hazırla ve gönder
        Mail::send([], [], function ($message) use ($request, $pdf, $transaction) {
            $message->to($request->email)
                    ->subject("Vomsis Dekont: İşlem #{$transaction->id}")
                    ->html("Merhaba,<br><br>İstemiş olduğunuz <b>{$transaction->bankAccount->bank->bank_name}</b> hesap hareket detayı (dekont) ektedir.<br><br>İyi çalışmalar dileriz.<br><b>Vomsis FinTech</b>")
                    ->attachData($pdf->output(), "vomsis-dekont-{$transaction->id}.pdf", [
                        'mime' => 'application/pdf',
                    ]);
        });

        // 4. Başarı mesajıyla ekrana dön
        return back()->with('mesaj', "📧 Dekont başarıyla <b>{$request->email}</b> adresine gönderildi.");
    }
}