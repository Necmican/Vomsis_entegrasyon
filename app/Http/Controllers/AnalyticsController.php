<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'admin';
        $izinliBankalar = $user->allowed_banks ?? [];

        // 1. Yetki ve Görünürlük Kalkanı: Grafik sadece "Kasaya Dahil" ve yetki verilen bankaları alacak
        $accountsQuery = \App\Models\BankAccount::where('include_in_totals', true)
            ->where('is_visible', true)
            ->whereHas('bank', function($q) {
                $q->where('is_visible', true);
            });

        if (!$isAdmin) {
            $accountsQuery->whereIn('bank_id', $izinliBankalar);
        }

        $accounts = $accountsQuery->get();
        
        // URL'den seçili hesap ID'sini alıyoruz (Örn: ?account_id=5)
        $selectedAccountId = $request->input('account_id');

        // Toplam hesabı yapabilmek için varsa seçili hesabı, yoksa sistemdeki tüm yetkili hesapları tanımla
        $accountIds = $selectedAccountId ? [$selectedAccountId] : $accounts->pluck('id')->toArray();

        $startDateStr = '2025-12-09';
        $endDateStr   = '2026-02-09';

        $startCarbon = Carbon::parse($startDateStr);
        $endCarbon   = Carbon::parse($endDateStr);

        // Para birimi bazında hesap ID'lerini grupla
        $currencies = ['TL', 'USD', 'EUR'];
        $accountsByCurrency = [];
        foreach ($currencies as $cur) {
            if ($selectedAccountId) {
                $acc = $accounts->firstWhere('id', $selectedAccountId);
                $accountsByCurrency[$cur] = ($acc && $acc->currency === $cur) ? [$selectedAccountId] : [];
            } else {
                $accountsByCurrency[$cur] = $accounts->where('currency', $cur)->pluck('id')->toArray();
            }
        }

        // --- 1. GEÇMİŞ VERİLER SORGUSU (her kur için ayrı) ---
        // Tek sorgoda tüm işlemleri çek, PHP'de pointer yöntemiyle gün-gün hesapla.
        // orderBy('id','asc') ile aynı transaction_date'te deterministik sıra garantilenir (Dashboard ile aynı mantık).

        $allTxns = Transaction::whereIn('bank_account_id', $accountIds)
            ->where('transaction_date', '<=', $endDateStr . ' 23:59:59')
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'bank_account_id', 'transaction_date', 'balance']);

        $txnsByAccount = $allTxns->groupBy('bank_account_id');

        // Her kur için gün-gün geçmiş veri hesapla
        $historyByCurrency = [];
        foreach ($currencies as $cur) {
            $curAccountIds = $accountsByCurrency[$cur];
            if (empty($curAccountIds)) {
                $historyByCurrency[$cur] = collect();
                continue;
            }

            $lastBalance = array_fill_keys($curAccountIds, 0);
            $pointers    = array_fill_keys($curAccountIds, 0);
            $txnLists    = [];
            foreach ($curAccountIds as $accId) {
                $txnLists[$accId] = isset($txnsByAccount[$accId])
                    ? $txnsByAccount[$accId]->values()
                    : collect();
            }

            $history     = collect();
            $currentDate = $startCarbon->copy();
            while ($currentDate->lte($endCarbon)) {
                $dateCutoff = $currentDate->format('Y-m-d') . ' 23:59:59';
                foreach ($curAccountIds as $accId) {
                    $ptr  = $pointers[$accId];
                    $list = $txnLists[$accId];
                    while ($ptr < $list->count() && $list[$ptr]->transaction_date <= $dateCutoff) {
                        $lastBalance[$accId] = $list[$ptr]->balance;
                        $ptr++;
                    }
                    $pointers[$accId] = $ptr;
                }
                $history->put($currentDate->format('Y-m-d'), array_sum($lastBalance));
                $currentDate->addDay();
            }
            $historyByCurrency[$cur] = $history;
        }

        // Geriye dönük uyumluluk: tek grafik için TL history'yi ana history olarak gönder
        $historyData = $historyByCurrency['TL'];

        // --- 2. YAPAY ZEKA SORGUSU (BATCH) ---
        $forecast = ['TL' => [], 'USD' => [], 'EUR' => []];
        $batchPayload = ['accounts' => []];
        
        foreach ($currencies as $cur) {
            $curHistory = $historyByCurrency[$cur];
            if ($curHistory->count() > 3) {
                $batchPayload['accounts'][$cur] = [
                    'dates' => $curHistory->keys()->toArray(),
                    'balances' => $curHistory->values()->toArray(),
                    'days_to_predict' => 15
                ];
            }
        }

        if (!empty($batchPayload['accounts'])) {
            try {
                // Batch endpoint'e gönder
                $response = Http::timeout(15)->post('http://python_ml:8000/api/forecast_batch', $batchPayload);

                if ($response->successful()) {
                    $batchResult = $response->json('forecasts');
                    foreach ($currencies as $cur) {
                        if (isset($batchResult[$cur])) {
                            $forecast[$cur] = $batchResult[$cur];
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Yapay Zeka Servis Hatası: " . $e->getMessage());
            }
        }

        // --- 3. ETİKET/PASTA SORGUSU ---
        $tagQuery = DB::table('pos_transaction_tag')
            ->join('tags', 'pos_transaction_tag.tag_id', '=', 'tags.id')
            ->join('transactions', 'pos_transaction_tag.pos_transaction_id', '=', 'transactions.id')
            ->where('transactions.amount', '<', 0); // Sadece giderler
            
        if ($selectedAccountId) {
            $tagQuery->where('transactions.bank_account_id', $selectedAccountId);
        }

        $tagStats = $tagQuery->select('tags.name', DB::raw('SUM(ABS(transactions.amount)) as total'))
            ->groupBy('tags.name')
            ->get();

        return view('analytics.index', [
            'accounts'          => $accounts,
            'selectedAccountId' => $selectedAccountId,
            'history'           => $historyData,
            'historyTL'         => $historyByCurrency['TL'],
            'historyUSD'        => $historyByCurrency['USD'],
            'historyEUR'        => $historyByCurrency['EUR'],
            'forecastTL'        => $forecast['TL'],
            'forecastUSD'       => $forecast['USD'],
            'forecastEUR'       => $forecast['EUR'],
            'tagStats'          => $tagStats,
        ]);
    }
}