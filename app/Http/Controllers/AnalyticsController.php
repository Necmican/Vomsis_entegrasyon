<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\BankAccount;
class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'admin';
        $izinliBankalar = $user->allowed_banks ?? [];

        // 1. Yetki ve Görünürlük Kalkanı: Grafik sadece "Kasaya Dahil" ve yetki verilen bankaları alacak
        $accountsQuery = BankAccount::where('include_in_totals', true)
            ->where('is_visible', true)
            ->whereHas('bank', function ($q) {
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

        // Kullanıcının seçtiği zaman aralığı (15 gün, 1 ay, 3 ay, 6 ay, 1 yıl, tümü)
        $timeframe = $request->input('timeframe', '15_days');

        // Veritabanındaki mutlak en son işlem tarihini bul (Dashboard'un bittiği yer)
        $maxDbDateStr = Transaction::whereIn('bank_account_id', $accountIds)->max('transaction_date');
        $endCarbon = $maxDbDateStr ? Carbon::parse($maxDbDateStr)->endOfDay() : Carbon::now()->endOfDay();

        $startCarbon = $endCarbon->copy()->subDays(15)->startOfDay();

        if ($timeframe === '1_month') {
            $startCarbon = $endCarbon->copy()->subMonth()->startOfDay();
        } elseif ($timeframe === '3_months') {
            $startCarbon = $endCarbon->copy()->subMonths(3)->startOfDay();
        } elseif ($timeframe === '6_months') {
            $startCarbon = $endCarbon->copy()->subMonths(6)->startOfDay();
        } elseif ($timeframe === '1_year') {
            $startCarbon = $endCarbon->copy()->subYear()->startOfDay();
        } elseif ($timeframe === 'all') {
            $minDate = Transaction::whereIn('bank_account_id', $accountIds)->min('transaction_date');
            $startCarbon = $minDate ? Carbon::parse($minDate)->startOfDay() : $endCarbon->copy()->subYear()->startOfDay();
        }

        $startDateStr = $startCarbon->format('Y-m-d');
        $endDateStr = $endCarbon->format('Y-m-d');

        // Hesapları kur bazında grupla (TL, USD, EUR)
        // Her kur için ayrı grafik ve tahmin üretilecek
        $currencies = ['TL', 'USD', 'EUR'];
        $accountsByCurrency = [];
        foreach ($currencies as $cur) {
            if ($selectedAccountId) {
                $acc = $accounts->firstWhere('id', $selectedAccountId);
                $isMatch = $acc && ($acc->currency === $cur || ($cur === 'TL' && $acc->currency === 'TRY'));
                $accountsByCurrency[$cur] = $isMatch ? [$selectedAccountId] : [];
            } else {
                $accountsByCurrency[$cur] = $accounts->filter(function ($a) use ($cur) {
                    return $a->currency === $cur || ($cur === 'TL' && $a->currency === 'TRY');
                })->pluck('id')->toArray();
            }
        }

        // --- 1. GEÇMİŞ VERİLER SORGUSU (her kur için ayrı) ---
        // Tüm işlemleri tarih sırasıyla çek (başlangıç filtresi YOK - kullanıcı istedi).
        // description eklendi: virman/iç transfer tespiti için gerekli.
        $allTxns = Transaction::whereIn('bank_account_id', $accountIds)
            ->where('transaction_date', '<=', $endDateStr . ' 23:59:59')
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'bank_account_id', 'transaction_date', 'balance', 'amount', 'description']);

        $txnsByAccount = $allTxns->groupBy('bank_account_id');

        // Virman/İç transfer tespiti: bu işlemler bakiyeyi etkiler ama gelir/gider'e DAHİL EDİLMEZ.
        // Kur bazlı virman sayısı ve hacmi hesaplanır.
        $virmanTxns = $allTxns->filter(function ($t) {
            $desc = mb_strtolower($t->description ?? '', 'UTF-8');
            return str_contains($desc, 'virman') || str_contains($desc, 'vİrman');
        });
        $virmanCount = $virmanTxns->count();

        // Kur bazlı virman hacimleri
        $virmanVolumes = [];
        foreach ($currencies as $cur) {
            $curAccIds = $accountsByCurrency[$cur] ?? [];
            $curVirmanlar = $virmanTxns->whereIn('bank_account_id', $curAccIds);
            if ($curVirmanlar->isNotEmpty()) {
                $virmanVolumes[$cur] = [
                    'count' => $curVirmanlar->count(),
                    'income' => round($curVirmanlar->where('amount', '>', 0)->sum('amount'), 2),
                    'expense' => round(abs($curVirmanlar->where('amount', '<', 0)->sum('amount')), 2),
                ];
            }
        }

        // Her kur için gün-gün geçmiş veri hesapla ve boş mu kontrolü
        $historyByCurrency = [];
        foreach ($currencies as $cur) {
            $curAccountIds = $accountsByCurrency[$cur];
            if (empty($curAccountIds)) {
                $historyByCurrency[$cur] = collect();
                continue;
            }

            // Hesap başlangıç bakiyeleri:
            // - İşlemi OLAN hesaplar: 0 ile başlar, pointer ilk işlemde doğru değeri atar
            // - İşlemi OLMAYAN hesaplar: Dashboard'un da kullandığı BankAccount.balance
            $lastBalance = [];
            foreach ($curAccountIds as $accId) {
                $hasTxns = isset($txnsByAccount[$accId]) && $txnsByAccount[$accId]->isNotEmpty();
                if ($hasTxns) {
                    $lastBalance[$accId] = 0;
                } else {
                    $acc = $accounts->firstWhere('id', $accId);
                    $lastBalance[$accId] = floatval($acc->balance ?? 0);
                }
            }

            // Pointer sistemi: her hesap için işlem listesinde kaçıncı sırada olduğumuzu tutar
            // Böylece aynı işlemi tekrar taramak zorunda kalmayız (performans optimizasyonu)
            $pointers = array_fill_keys($curAccountIds, 0);
            $txnLists = [];
            foreach ($curAccountIds as $accId) {
                $txnLists[$accId] = isset($txnsByAccount[$accId])
                    ? $txnsByAccount[$accId]->values()
                    : collect();
            }

            // AŞAMA 1: Grafik başlangıcından ÖNCEKİ tüm işlemleri hızlıca işle
            // (Bu, grafiğin ilk gününde doğru açılış bakiyesini verir)
            foreach ($curAccountIds as $accId) {
                $ptr = $pointers[$accId];
                $list = $txnLists[$accId];
                while ($ptr < $list->count() && Carbon::parse($list[$ptr]->transaction_date)->lt($startCarbon)) {
                    $lastBalance[$accId] = floatval($list[$ptr]->balance);
                    $ptr++;
                }
                $pointers[$accId] = $ptr;
            }

            // AŞAMA 2: Gün-gün grafik noktalarını çiz + günlük gelir/gider hesapla
            $history = collect();
            $currentDate = $startCarbon->copy()->startOfDay();
            while ($currentDate->lte($endCarbon)) {
                $dateCutoff = $currentDate->copy()->endOfDay();
                $dailyIncome = 0;
                $dailyExpense = 0;

                foreach ($curAccountIds as $accId) {
                    $ptr = $pointers[$accId];
                    $list = $txnLists[$accId];
                    while ($ptr < $list->count() && Carbon::parse($list[$ptr]->transaction_date)->lte($dateCutoff)) {
                        $lastBalance[$accId] = floatval($list[$ptr]->balance);
                        // Virman işlemleri bakiyeyi etkiler ama gelir/gider'e eklenmez
                        $desc = mb_strtolower($list[$ptr]->description ?? '', 'UTF-8');
                        $isVirman = str_contains($desc, 'virman') || str_contains($desc, 'vİrman');
                        if (!$isVirman) {
                            $amt = floatval($list[$ptr]->amount ?? 0);
                            if ($amt > 0) {
                                $dailyIncome += $amt;
                            } else {
                                $dailyExpense += abs($amt);
                            }
                        }
                        $ptr++;
                    }
                    $pointers[$accId] = $ptr;
                }

                $history->put($currentDate->format('Y-m-d'), [
                    'balance' => array_sum($lastBalance),
                    'income' => round($dailyIncome, 2),
                    'expense' => round($dailyExpense, 2),
                ]);
                $currentDate->addDay();
            }
            $historyByCurrency[$cur] = $history;
        }

        // --- 2. YAPAY ZEKA SORGUSU (BATCH — Prophet Modeli) ---
        // ÖNEMLİ: Grafik sadece seçili zaman aralığını gösterir, ama ML modeline
        // TÜM tarihsel veri gönderilmelidir (backtesting ve mevsimsellik için).
        $forecast = ['TL' => [], 'USD' => [], 'EUR' => []];
        $accuracies = ['TL' => null, 'USD' => null, 'EUR' => null];
        $batchPayload = ['accounts' => []];

        foreach ($currencies as $cur) {
            $curAccountIds = $accountsByCurrency[$cur];
            if (empty($curAccountIds))
                continue;

            // Tüm geçmiş veriden ML payload'ı oluştur (grafik aralığından BAĞIMSIZ)
            // allTxns zaten tüm verileri içeriyor (startDate filtresi yok)
            $mlLastBalance = [];
            foreach ($curAccountIds as $accId) {
                $hasTxns = isset($txnsByAccount[$accId]) && $txnsByAccount[$accId]->isNotEmpty();
                $mlLastBalance[$accId] = $hasTxns ? 0 : floatval($accounts->firstWhere('id', $accId)->balance ?? 0);
            }

            $mlDates = [];
            $mlBalances = [];
            $mlIncomes = [];
            $mlExpenses = [];

            // İlk işlemin tarihini bul
            $minDateForCur = null;
            foreach ($curAccountIds as $accId) {
                $list = isset($txnsByAccount[$accId]) ? $txnsByAccount[$accId]->values() : collect();
                if ($list->isNotEmpty()) {
                    $firstDate = Carbon::parse($list[0]->transaction_date)->startOfDay();
                    if ($minDateForCur === null || $firstDate->lt($minDateForCur)) {
                        $minDateForCur = $firstDate->copy();
                    }
                }
            }

            if ($minDateForCur === null)
                continue;

            // Pointer yöntemiyle tüm geçmişi gün-gün tara
            $mlPointers = array_fill_keys($curAccountIds, 0);
            $mlTxnLists = [];
            foreach ($curAccountIds as $accId) {
                $mlTxnLists[$accId] = isset($txnsByAccount[$accId])
                    ? $txnsByAccount[$accId]->values()
                    : collect();
            }

            $mlCurrentDate = $minDateForCur->copy()->startOfDay();
            while ($mlCurrentDate->lte($endCarbon)) {
                $dateCutoff = $mlCurrentDate->copy()->endOfDay();
                $dayIncome = 0;
                $dayExpense = 0;

                foreach ($curAccountIds as $accId) {
                    $ptr = $mlPointers[$accId];
                    $list = $mlTxnLists[$accId];
                    while ($ptr < $list->count() && Carbon::parse($list[$ptr]->transaction_date)->lte($dateCutoff)) {
                        $mlLastBalance[$accId] = floatval($list[$ptr]->balance);
                        // Virman işlemleri ML gelir/giderine DAHİL EDİLMEZ
                        $desc = mb_strtolower($list[$ptr]->description ?? '', 'UTF-8');
                        $isVirman = str_contains($desc, 'virman') || str_contains($desc, 'vİrman');
                        if (!$isVirman) {
                            $amt = floatval($list[$ptr]->amount ?? 0);
                            if ($amt > 0)
                                $dayIncome += $amt;
                            else
                                $dayExpense += abs($amt);
                        }
                        $ptr++;
                    }
                    $mlPointers[$accId] = $ptr;
                }

                $mlDates[] = $mlCurrentDate->format('Y-m-d');
                $mlBalances[] = array_sum($mlLastBalance);
                $mlIncomes[] = round($dayIncome, 2);
                $mlExpenses[] = round($dayExpense, 2);
                $mlCurrentDate->addDay();
            }

            if (count($mlDates) > 3) {
                // Özel gün flag'leri: Prophet'e ek regressor olarak gönderilir
                // Model bu bilgiyi kullanarak maaş günü, ay sonu gibi döngüleri yakalar
                $specialDays = [];
                foreach ($mlDates as $dateStr) {
                    $d = Carbon::parse($dateStr);
                    $specialDays[] = [
                        'is_month_end' => $d->day >= 25,
                        'is_month_start' => $d->day <= 5,
                        'is_weekend' => $d->isWeekend(),
                        'is_quarter_end' => in_array($d->month, [3, 6, 9, 12]) && $d->day >= 20,
                    ];
                }

                // Python ML servisine gönderilecek JSON payload
                $batchPayload['accounts'][$cur] = [
                    'dates' => $mlDates,
                    'balances' => $mlBalances,
                    'incomes' => $mlIncomes,
                    'expenses' => $mlExpenses,
                    'special_days' => $specialDays,
                    'days_to_predict' => 15,
                ];
            }
        }

        // Python ML servisine HTTP POST isteği at (Docker iç ağı üzerinden)
        if (!empty($batchPayload['accounts'])) {
            try {
                // Prophet modeline daha fazla zaman ver (tahmin + backtesting yapıyor)
                $response = Http::timeout(45)->post('http://python_ml:8000/api/forecast_batch', $batchPayload);

                if ($response->successful()) {
                    $batchResult = $response->json();
                    $forecastData = $batchResult['forecasts'] ?? [];
                    $accData = $batchResult['accuracies'] ?? [];
                    foreach ($currencies as $cur) {
                        if (isset($forecastData[$cur])) {
                            $forecast[$cur] = $forecastData[$cur];
                        }
                        if (isset($accData[$cur])) {
                            $accuracies[$cur] = $accData[$cur];
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Yapay Zeka Servis Hatası: " . $e->getMessage());
            }
        }

        // --- 3. ETİKET/PASTA SORGUSU (virman hariç & Kur-Tip ayrımı yapılmış) ---
        // Pasta grafiği için: hangi etiket ne kadar gelir/gider toplamış?
        // Ham SQL kullanıldığı için Global Scope (is_real filtresi) otomatik çalışmaz
        $isRealContext = auth()->user()->is_real_data ? 1 : 0;

        // transaction_tag pivot tablosu üzerinden JOIN zinciri:
        // transaction_tag → tags (etiket adı) → transactions (tutar) → bank_accounts (kur)
        $tagQuery = DB::table('transaction_tag')
            ->join('tags', 'transaction_tag.tag_id', '=', 'tags.id')
            ->join('transactions', 'transaction_tag.transaction_id', '=', 'transactions.id')
            ->join('bank_accounts', 'transactions.bank_account_id', '=', 'bank_accounts.id')
            ->where('transactions.is_real', $isRealContext)
            ->where('transactions.description', 'NOT LIKE', '%virman%')
            ->where('transactions.description', 'NOT LIKE', '%VIRMAN%')
            ->where('transactions.description', 'NOT LIKE', '%VİRMAN%');

        if ($selectedAccountId) {
            $tagQuery->where('transactions.bank_account_id', $selectedAccountId);
        } else {
            $tagQuery->whereIn('transactions.bank_account_id', $accountIds);
        }

        $rawTagStats = $tagQuery->select(
            'tags.name',
            'bank_accounts.currency',
            DB::raw('SUM(CASE WHEN transactions.amount > 0 THEN transactions.amount ELSE 0 END) as total_income'),
            DB::raw('SUM(CASE WHEN transactions.amount < 0 THEN ABS(transactions.amount) ELSE 0 END) as total_expense')
        )
            ->groupBy('tags.name', 'bank_accounts.currency')
            ->get();

        $tagStats = [
            'TL' => ['income' => [], 'expense' => []],
            'USD' => ['income' => [], 'expense' => []],
            'EUR' => ['income' => [], 'expense' => []]
        ];

        foreach ($rawTagStats as $stat) {
            $cur = $stat->currency;
            if (!isset($tagStats[$cur]))
                continue;

            if ($stat->total_income > 0) {
                $tagStats[$cur]['income'][] = ['name' => $stat->name, 'total' => round($stat->total_income, 2)];
            }
            if ($stat->total_expense > 0) {
                $tagStats[$cur]['expense'][] = ['name' => $stat->name, 'total' => round($stat->total_expense, 2)];
            }
        }

        // Tüm hesaplanmış verileri Blade şablonuna gönder
        return view('analytics.index', [
            'accounts' => $accounts,
            'selectedAccountId' => $selectedAccountId,
            'history' => $historyByCurrency['TL'],
            'historyTL' => $historyByCurrency['TL'],
            'historyUSD' => $historyByCurrency['USD'],
            'historyEUR' => $historyByCurrency['EUR'],
            'forecastTL' => $forecast['TL'],
            'forecastUSD' => $forecast['USD'],
            'forecastEUR' => $forecast['EUR'],
            'accuracyTL' => $accuracies['TL'],
            'accuracyUSD' => $accuracies['USD'],
            'accuracyEUR' => $accuracies['EUR'],
            'tagStats' => $tagStats,
            'virmanCount' => $virmanCount,
            'virmanVolumes' => $virmanVolumes ?? [],
        ]);
    }
}