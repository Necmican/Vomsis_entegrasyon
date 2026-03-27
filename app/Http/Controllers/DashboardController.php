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
        $izinliEtiketler = $user->allowed_tags ?? [];

        // ========================================================================
        // YENİ: YÖNETİM PANELİ İÇİN TÜM BANKALARI ÇEK (Sadece Admin Görür)
        // ========================================================================
        $allSettingsBanks = $isAdmin ? \App\Models\Bank::with('bankAccounts')->orderBy('bank_name')->get() : collect();

        // ========================================================================
        // 2. SOL MENÜ İÇİN BANKALARI ÇEK (Görünürlük ve Yetki Filtreli)
        // ========================================================================
        $banksQuery = \App\Models\Bank::with(['bankAccounts' => function($q) {
            $q->where('is_visible', true);
        }])->where('is_visible', true)->orderBy('bank_name');

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
                    'filteredSummaries'  => collect([]),
                    'allSettingsBanks'   => collect([])
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
        // 4. GENEL KASA TOPLAMLARI (GÖRÜNÜRLÜK VE KASAYA DAHİL ETME FİLTRELİ)
        // ========================================================================
        $totalsQuery = \App\Models\BankAccount::where('include_in_totals', true)
                                  ->where('is_visible', true)
                                  ->whereHas('bank', function($q) {
                                      $q->where('is_visible', true);
                                  })
                                  ->selectRaw('currency, SUM(balance) as total')
                                  ->groupBy('currency');
        
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
            $activeBankAccounts = $activeBank->bankAccounts->where('is_visible', true);
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
        $query = \App\Models\Transaction::with(['bankAccount.bank', 'transactionType', 'tags']);

        $query->whereHas('bankAccount', function ($q) {
            $q->where('is_visible', true)->whereHas('bank', function($subQ) {
                $subQ->where('is_visible', true);
            });
        });

        if (!$isAdmin) {
            $query->whereHas('bankAccount', function ($q) use ($izinliBankalar) {
                $q->whereIn('bank_id', $izinliBankalar);
            });

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
        // 7. KULLANICI ARAMA VE ÇOKLU AI FİLTRELERİ (ANA TABLO SORGUSU)
        // ========================================================================
        if ($request->filled('search')) {
            // Arama kelimesini boşluklardan parçala (Örn: "Kardeşler gıda" => ["Kardeşler", "gıda"])
            $searchTerms = explode(' ', $request->search);
            
            $query->where(function($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $term = trim($term);
                    if (!empty($term)) {
                        // Her kelime için: Ya açıklamada geçsin YA DA banka adında geçsin
                        $q->where(function($subQ) use ($term) {
                            $subQ->where('description', 'like', '%' . $term . '%')
                                 ->orWhereHas('bankAccount.bank', function($bankQ) use ($term) {
                                     $bankQ->where('bank_name', 'like', '%' . $term . '%');
                                 });
                        });
                    }
                }
            });
        }
        
        if ($request->filled('currency')) {
            $query->whereHas('bankAccount', function ($q) use ($request) {
                $q->where('currency', $request->currency);
            });
        }
        
        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->end_date);
        }
        if ($request->filled('tag_name')) {
            $tagName = $request->tag_name;
            $query->whereHas('tags', function ($q) use ($tagName) {
                $q->where('name', 'like', '%' . $tagName . '%');
            });
        }
        if ($request->filled('type_name')) {
            if (strtolower($request->type_name) === 'gelir') {
                $query->where('amount', '>', 0);
            } elseif (strtolower($request->type_name) === 'gider') {
                $query->where('amount', '<', 0);
            }
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
        // 8. DİNAMİK GELİR/GİDER HESABI (ÖZET KUTULARI)
        // ========================================================================


        $summaryQuery = \App\Models\Transaction::join('bank_accounts', 'transactions.bank_account_id', '=', 'bank_accounts.id')
            ->join('banks', 'bank_accounts.bank_id', '=', 'banks.id')
            ->where('bank_accounts.include_in_totals', true)
            ->where('bank_accounts.is_visible', true)
            ->where('banks.is_visible', true);

        if (!$isAdmin) {
            $summaryQuery->whereIn('bank_accounts.bank_id', $izinliBankalar);
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
            $searchTerms = explode(' ', $request->search);
            
            $summaryQuery->where(function($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $term = trim($term);
                    if (!empty($term)) {
                        $q->where(function($subQ) use ($term) {
                            $subQ->where('transactions.description', 'like', '%' . $term . '%')
                                 ->orWhere('banks.bank_name', 'like', '%' . $term . '%');
                        });
                    }
                }
            });
        }
        
        if ($request->filled('currency')) {
            $summaryQuery->where('bank_accounts.currency', $request->currency);
        }
        if ($request->filled('start_date')) {
            $summaryQuery->whereDate('transactions.transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $summaryQuery->whereDate('transactions.transaction_date', '<=', $request->end_date);
        }
        if ($request->filled('tag_name')) {
            $tagName = $request->tag_name;
            if (!$request->filled('tag_id')) {
                $summaryQuery->join('pos_transaction_tag', 'transactions.id', '=', 'pos_transaction_tag.pos_transaction_id');
            }
            $summaryQuery->join('tags', 'pos_transaction_tag.tag_id', '=', 'tags.id')
                         ->where('tags.name', 'like', '%' . $tagName . '%');
        }
        if ($request->filled('type_name')) {
            if (strtolower($request->type_name) === 'gelir') {
                $summaryQuery->where('transactions.amount', '>', 0);
            } elseif (strtolower($request->type_name) === 'gider') {
                $summaryQuery->where('transactions.amount', '<', 0);
            }
        }
        if ($request->filled('type_code')) {
            $summaryQuery->where('transactions.transaction_type_code', $request->type_code);
        }
        if ($request->filled('account_id')) {
            $summaryQuery->where('transactions.bank_account_id', $request->account_id);
        }
        if ($request->filled('tag_id')) {
            if (!$request->filled('tag_name')) {
                 $summaryQuery->join('pos_transaction_tag', 'transactions.id', '=', 'pos_transaction_tag.pos_transaction_id');
            }
            $summaryQuery->where('pos_transaction_tag.tag_id', $request->tag_id);
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
        $transactionTypes = \App\Models\TransactionType::orderBy('name')->get(); 
        
        if ($isAdmin) {
            $allTags = \App\Models\Tag::orderBy('name')->get(); 
        } else {
            $allTags = \App\Models\Tag::whereIn('id', $izinliEtiketler)->orderBy('name')->get();
        }

        $transactions = $query->orderBy('transaction_date', 'desc')
                              ->paginate(20)
                              ->withQueryString();

        return view('dashboard', compact(
            'transactions', 'totals', 'transactionTypes', 'banks', 
            'filteredSummaries', 'activeBank', 'activeBankSummary', 'activeBankAccounts',
            'allTags', 'allSettingsBanks'
        ));
    }
   // ========================================================================
    // BANKA VE KASA AYARLARINI KAYDET
    // ========================================================================
    public function updateBankSettings(Request $request)
    {
        try {
            
            \App\Models\Bank::query()->update(['is_visible' => false]);
            \App\Models\BankAccount::query()->update(['is_visible' => false, 'include_in_totals' => false]);

            // 2. HTML Formundan gelen gelişmiş dizileri (klasörleri) alıyoruz
            $banks = $request->input('banks', []);
            $accounts = $request->input('accounts', []);

            // 3. İçinde işaret (1) olan şalterlerin ID'lerini zekice ayıklıyoruz
            $visibleBankIds = array_keys(array_filter($banks, function($b) { return isset($b['is_visible']); }));
            
            $visibleAccountIds = array_keys(array_filter($accounts, function($a) { return isset($a['is_visible']); }));
            $includeTotalIds = array_keys(array_filter($accounts, function($a) { return isset($a['include_in_totals']); }));

            // 4. Sadece açık olan şalterlerin ID'lerini veritabanında "Açık (True)" yapıyoruz
            if (!empty($visibleBankIds)) {
                \App\Models\Bank::whereIn('id', $visibleBankIds)->update(['is_visible' => true]);
            }
            if (!empty($visibleAccountIds)) {
                \App\Models\BankAccount::whereIn('id', $visibleAccountIds)->update(['is_visible' => true]);
            }
            if (!empty($includeTotalIds)) {
                \App\Models\BankAccount::whereIn('id', $includeTotalIds)->update(['include_in_totals' => true]);
            }

            return redirect()->back()->with('success', 'Kasa ayarları başarıyla güncellendi!');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ayarlar kaydedilirken bir hata oluştu: ' . $e->getMessage());
        }
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

            // 2. Etiket yetkisi kontrolü
            if ($transaction->tags->isNotEmpty()) {
                $izinliEtiketler = $user->allowed_tags ?? [];
                
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