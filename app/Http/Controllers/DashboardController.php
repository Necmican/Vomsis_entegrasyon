<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use App\Models\TransactionType;
use App\Models\Bank;
use App\Models\Tag; 
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // ========================================================================
        // 1. GİRİŞ YAPAN KULLANICIYI TANI VE İZİNLERİ BELİRLE
        // ========================================================================
        $user = auth()->user();
        $isAdmin = $user->role === 'admin';
        $izinliBankalar = $user->allowed_banks ?? [];
        $izinliEtiketler = $user->allowed_tags ?? []; // YENİ: Etiket izinlerini en baştan alıyoruz

        // ========================================================================
        // 2. SOL MENÜ İÇİN BANKALARI ÇEK (YETKİ FİLTRELİ)
        // ========================================================================
        $banksQuery = Bank::with('bankAccounts')->orderBy('bank_name');

        if (!$isAdmin) {
            if (empty($izinliBankalar)) {
                return view('dashboard', [
                    'banks'              => collect([]),
                    'transactions'       => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20),
                    'totals'             => [],
                    'activeBank'         => null,
                    'activeBankAccounts' => collect(),
                    'activeBankSummary'  => [],
                    'transactionTypes'   => collect([]),
                    'allTags'            => collect([]),
                    'filteredSummaries'  => collect([])
                ])->with('error', 'Henüz hiçbir bankayı görüntüleme yetkiniz bulunmamaktadır. Lütfen yöneticinizle iletişime geçin.');
            }
            $banksQuery->whereIn('id', $izinliBankalar);
        }

        $banks = $banksQuery->get();

        // ========================================================================
        // 3. AKTİF BANKA (SEÇİLEN VEYA VARSAYILAN) KONTROLÜ VE GÜVENLİK
        // ========================================================================
        $activeBank = null;
        
        if ($request->has('bank_id')) {
            $istenenBankId = $request->input('bank_id');

            if (!$isAdmin && !in_array($istenenBankId, $izinliBankalar)) {
                return redirect()->route('dashboard')->with('error', 'Bu bankanın hesap hareketlerini görüntüleme yetkiniz bulunmamaktadır.');
            }

            $activeBank = $banks->firstWhere('id', $istenenBankId);
        }

        // ========================================================================
        // 4. GENEL KASA TOPLAMLARI (YETKİ FİLTRELİ)
        // ========================================================================
        $totalsQuery = BankAccount::selectRaw('currency, SUM(balance) as total')->groupBy('currency');
        
        if (!$isAdmin) {
            $totalsQuery->whereIn('bank_id', $izinliBankalar);
        }
        $totals = $totalsQuery->pluck('total', 'currency');

        // ========================================================================
        // 5. AKTİF BANKA DETAYLARI (HESAPLAR VE KURLAR)
        // ========================================================================
        $activeBankSummary = [];
        $activeBankAccounts = collect();

        if ($activeBank) {
            $activeBankAccounts = $activeBank->bankAccounts;
            $activeBankSummary = $activeBankAccounts->groupBy('currency')->map(function ($accounts) {
                return [
                    'count' => $accounts->count(),
                    'total' => $accounts->sum('balance')
                ];
            });
        }

        // ========================================================================
        // 6. ANA İŞLEM (TRANSACTION) SORGUSUNU BAŞLAT
        // ========================================================================
        $query = Transaction::with(['bankAccount.bank', 'transactionType', 'tags']);

        if (!$isAdmin) {
            // A. Banka Yetki Kilidi
            $query->whereHas('bankAccount', function ($q) use ($izinliBankalar) {
                $q->whereIn('bank_id', $izinliBankalar);
            });

            // B. YENİ: Etiket Yetki Kilidi (Etiketsizler VEYA yetkili olduğu etiketler)
            $query->where(function ($q) use ($izinliEtiketler) {
                $q->doesntHave('tags')
                  ->orWhereHas('tags', function ($subQ) use ($izinliEtiketler) {
                      $subQ->whereIn('tags.id', $izinliEtiketler);
                  });
            });
        }

        if ($activeBank) {
            $query->whereHas('bankAccount', function ($q) use ($activeBank) {
                $q->where('bank_id', $activeBank->id);
            });
        }

        // ========================================================================
        // 7. KULLANICI ARAMA VE DİNAMİK FİLTRELERİ
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
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('tags.id', $request->tag_id);
            });
        }

        // ========================================================================
        // 8. DİNAMİK GELİR/GİDER HESABI (YETKİ KORUMALI)
        // ========================================================================
        $summaryQuery = Transaction::join('bank_accounts', 'transactions.bank_account_id', '=', 'bank_accounts.id');

        if (!$isAdmin) {
            // A. Banka Yetki Kilidi
            $summaryQuery->whereIn('bank_accounts.bank_id', $izinliBankalar);

            // B. YENİ: Etiket Yetki Kilidi (Üstteki gelir/gider hesabı şaşmasın diye)
            $summaryQuery->where(function ($q) use ($izinliEtiketler) {
                $q->doesntHave('tags')
                  ->orWhereHas('tags', function ($subQ) use ($izinliEtiketler) {
                      $subQ->whereIn('tags.id', $izinliEtiketler);
                  });
            });
        }

        if ($activeBank) {
            $summaryQuery->where('bank_accounts.bank_id', $activeBank->id);
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

        // ========================================================================
        // 9. SABİT LİSTELER VE SAYFALAMA
        // ========================================================================
        $transactionTypes = TransactionType::orderBy('name')->get(); 
        
        if ($isAdmin) {
            $allTags = Tag::orderBy('name')->get(); 
        } else {
            $allTags = Tag::whereIn('id', $izinliEtiketler)->orderBy('name')->get();
        }

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
    // SAĞ TIK: GÖRÜNTÜLE, YAZDIR, PDF İNDİR VE E-POSTA İŞLEMLERİ (GÜVENLİ)
    // ========================================================================

    private function checkTransactionAccess($transaction)
    {
        $user = auth()->user();
        if ($user->role !== 'admin') {
            // 1. Banka yetkisi kontrolü
            $izinliBankalar = $user->allowed_banks ?? [];
            if (!in_array($transaction->bankAccount->bank_id, $izinliBankalar)) {
                abort(403, 'Bu işlemi görüntüleme veya işlem yapma yetkiniz bulunmamaktadır.');
            }

            // 2. YENİ: Etiket yetkisi kontrolü (Adam PDF olarak indirmesin diye)
            if ($transaction->tags->isNotEmpty()) {
                $izinliEtiketler = $user->allowed_tags ?? [];
                
                // İşlemdeki etiketlerden en az biri, adamın izinli listesinde var mı diye bak
                $hasAllowedTag = $transaction->tags->pluck('id')->intersect($izinliEtiketler)->isNotEmpty();
                
                if (!$hasAllowedTag) {
                    abort(403, 'Bu işlemi görüntüleme yetkiniz bulunmamaktadır (Gizli İşlem).');
                }
            }
        }
    }

    public function viewTransaction($id)
    {
        $transaction = Transaction::with(['bankAccount.bank', 'transactionType', 'tags'])->findOrFail($id);
        $this->checkTransactionAccess($transaction); 
        
        return view('pdf.transaction', compact('transaction'));
    }

    public function printTransaction($id)
    {
        $transaction = Transaction::with(['bankAccount.bank', 'transactionType', 'tags'])->findOrFail($id);
        $this->checkTransactionAccess($transaction); 
        
        return view('pdf.transaction', compact('transaction'))->with('is_print', true);
    }

    public function downloadPdf($id)
    {
        $transaction = Transaction::with(['bankAccount.bank', 'transactionType', 'tags'])->findOrFail($id);
        $this->checkTransactionAccess($transaction); 

        $pdf = Pdf::loadView('pdf.transaction', compact('transaction'));
        $pdf->setOptions(['defaultFont' => 'DejaVu Sans']);

        return $pdf->download('vomsis-islem-' . $transaction->id . '.pdf');
    }

    public function sendPdfEmail(Request $request, $id)
    {
        $request->validate(['email' => 'required|email']);

        $transaction = Transaction::with(['bankAccount.bank', 'transactionType', 'tags'])->findOrFail($id);
        $this->checkTransactionAccess($transaction); 

        $pdf = Pdf::loadView('pdf.transaction', compact('transaction'));
        $pdf->setOptions(['defaultFont' => 'DejaVu Sans']);

        Mail::send([], [], function ($message) use ($request, $pdf, $transaction) {
            $message->to($request->email)
                    ->subject("Vomsis Dekont: İşlem #{$transaction->id}")
                    ->html("Merhaba,<br><br>İstemiş olduğunuz <b>{$transaction->bankAccount->bank->bank_name}</b> hesap hareket detayı (dekont) ektedir.<br><br>İyi çalışmalar dileriz.<br><b>Vomsis FinTech</b>")
                    ->attachData($pdf->output(), "vomsis-dekont-{$transaction->id}.pdf", [
                        'mime' => 'application/pdf',
                    ]);
        });

        return back()->with('mesaj', "📧 Dekont başarıyla <b>{$request->email}</b> adresine gönderildi.");
    }
}