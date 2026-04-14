<?php

use Illuminate\Support\Facades\Route;
use App\Services\VomsisService;
use App\Http\Controllers\DashboardController;
use App\Jobs\SyncVomsisJob;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\PaymentController; 
use App\Http\Controllers\TagController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController; 
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\AnalyticsController;
use Carbon\Carbon;

// ========================================================================
// A. GUEST (MİSAFİR) ROTALARI - Sadece giriş YAPMAMIŞ kişiler görebilir
// ========================================================================
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// ========================================================================
// B. AUTH (GÜVENLİ) ROTALAR - Sadece GİRİŞ YAPMIŞ kişiler görebilir
// ========================================================================
Route::middleware('auth')->group(function () {

    // Anasayfaya girenleri direkt Dashboard'a yönlendir
    Route::get('/', function () {
        return redirect('/dashboard');
    });

    // Çıkış İşlemi
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // --- YENİ EKLENEN: PERSONEL YÖNETİMİ ---
    Route::get('/kullanici/ekle', [UserController::class, 'create'])->name('users.create');
    Route::post('/kullanici/ekle', [UserController::class, 'store'])->name('users.store');

    // ========================================================================
    // 1. DASHBOARD & VERİ SENKRONİZASYONU
    // ========================================================================
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/arka-planda-cek', function () {
        SyncVomsisJob::dispatch();
        return redirect('/dashboard')->with('mesaj', 'Veri çekme işlemi arka planda başlatıldı! Sayfayı birazdan yenileyin.');
    });

    // ========================================================================
    // 2. SAĞ TIK (CONTEXT MENU) İŞLEMLERİ (PDF, YAZDIR, GÖRÜNTÜLE, E-POSTA)
    // ========================================================================
    Route::get('/islem/{id}/pdf', [DashboardController::class, 'downloadPdf'])->name('transaction.pdf');
    Route::get('/islem/{id}/goruntule', [DashboardController::class, 'viewTransaction'])->name('transaction.view');
    Route::get('/islem/{id}/yazdir', [DashboardController::class, 'printTransaction'])->name('transaction.print');
    Route::post('/islem/{id}/eposta-gonder', [DashboardController::class, 'sendPdfEmail'])->name('transaction.email');

    // ========================================================================
    // 3. ETİKET YÖNETİMİ
    // ========================================================================
    Route::post('/etiket/olustur', [TagController::class, 'store'])->name('tags.store');
    Route::post('/islem/{transactionId}/etiket-ekle', [TagController::class, 'attachTag']);
    Route::post('/islem/{transactionId}/etiket-cikar/{tagId}', [TagController::class, 'detachTag']);
    Route::post('/islem/toplu-etiket-ekle', [TagController::class, 'bulkAttachTags'])->name('tags.bulk.attach');
    Route::post('/islem/toplu-etiket-kaldir', [TagController::class, 'bulkDetachTags'])->name('tags.bulk.detach');

    // ========================================================================
    // 4. OTO-ETİKET (AUTO-TAG) YÖNETİMİ
    // ========================================================================
    Route::get('/oto-etiket', [TagController::class, 'autoTagIndex'])->name('auto-tag.index');
    Route::post('/oto-etiket/kural-kaydet', [TagController::class, 'saveAutoTagRule'])->name('auto-tag.save');
    Route::post('/oto-etiket/kume-etiketle', [TagController::class, 'applyCluster'])->name('auto-tag.apply-cluster');
    Route::post('/oto-etiket/geri-donuk-uygula', [TagController::class, 'applyAutoTagRules'])->name('auto-tag.apply');
    Route::delete('/oto-etiket/kural-sil/{id}', [TagController::class, 'deleteAutoTagRule'])->name('auto-tag.delete');

    // Kelime Hariç Tutma
    Route::post('/oto-etiket/kelime-haric-tut', [TagController::class, 'addExclusion'])->name('auto-tag.exclusion.add');
    Route::delete('/oto-etiket/kelime-haric-tut/{id}', [TagController::class, 'deleteExclusion'])->name('auto-tag.exclusion.delete');
    // AJAX re-clustering JSON endpoint
    Route::get('/oto-etiket/clusters-json', [TagController::class, 'clustersJson'])->name('auto-tag.clusters-json');

    // ── Hızlı Etiketleme ──────────────────────────────────────────────────────
    // 1. Anahtar kelimedeki eşleşen işlemleri önizle (JSON)
    Route::get('/oto-etiket/ara', [TagController::class, 'keywordPreview'])->name('auto-tag.keyword-preview');
    // 2. Etiket oluştur (yoksa) + eşleşen işlemleri etiketle
    Route::post('/oto-etiket/hizli-etiketle', [TagController::class, 'quickTagByKeyword'])->name('auto-tag.quick-tag');
    // 3. Eşleşen işlemleri soft-delete ile kaldır
    Route::delete('/oto-etiket/islemleri-sil', [TagController::class, 'deleteMatchingTransactions'])->name('auto-tag.delete-matching');

    // ========================================================================
    // 4. DIŞA AKTARIM (EXCEL & PDF TABLO)
    // ========================================================================
    Route::get('/export/pdf', [ExportController::class, 'exportPdf'])->name('export.pdf');
    Route::post('/export/excel', [ExportController::class, 'exportExcel'])->name('export.excel');
    
    // Arka Plan Kuyruk (Queue) Takip ve İndirme Rotaları
    Route::get('/export/status', [ExportController::class, 'checkStatus'])->name('export.status');
    Route::get('/export/download/{id}', [ExportController::class, 'downloadFile'])->name('export.download');

    // ========================================================================
    // 5. SANAL POS İŞLEMLERİ
    // ========================================================================
    Route::get('/odeme-yap', [PaymentController::class, 'index'])->name('payment.index');
    Route::post('/odeme-yap', [PaymentController::class, 'process']);
    Route::get('/sanal-pos-islemleri', [PaymentController::class, 'transactionsList'])->name('payment.list');

    Route::post('/odeme/bin-check', [PaymentController::class, 'binCheck'])->name('payment.bincheck');
    Route::post('/odeme/isle', [PaymentController::class, 'process'])->name('payment.process');
    Route::get('/sanal-pos/senkronize-et', [PaymentController::class, 'syncTransactions'])->name('payment.sync');  
    
    // ========================================================================
    // 6. GELİŞTİRİCİ TEST ROTALARI (Sadece Test Ortamı ve Yöneticiler)
    // ========================================================================
    Route::prefix('dev')->group(function () {
        Route::get('/test-token', function (VomsisService $vomsisService) {
            try { return "Tebrikler! Token: " . $vomsisService->getToken(); } catch (\Exception $e) { return "Hata: " . $e->getMessage(); }
        });
        Route::get('/test-banks', function (VomsisService $vomsisService) {
            try { return $vomsisService->syncBanks(); } catch (\Exception $e) { return "Hata: " . $e->getMessage(); }
        });
        Route::get('/test-accounts', function (VomsisService $vomsisService) {
            try { return $vomsisService->syncAccounts(); } catch (\Exception $e) { return "Hata: " . $e->getMessage(); }
        });
        Route::get('/test-transactions', function (VomsisService $vomsisService) {
            try { return $vomsisService->syncTransactions(); } catch (\Exception $e) { return "Hata: " . $e->getMessage(); }
        });
        Route::get('/test-types', function (VomsisService $vomsisService) {
            try { return $vomsisService->syncTransactionTypes(); } catch (\Exception $e) { return "Hata: " . $e->getMessage(); }
        });
        Route::get('/test-poses', function (VomsisService $vomsisService) {
            try { return $vomsisService->syncVirtualPoses(); } catch (\Exception $e) { return "Hata: " . $e->getMessage(); }
        });
        Route::get('/hata-test', function (VomsisService $vomsisService) {
            try { $vomsisService->syncTransactions(); return "İşlem Başarılı!"; } catch (\Exception $e) { return "HATA: " . $e->getMessage(); }
        });
        
        // ML ve Geçmiş Veri Testleri
        Route::get('/yapay-zeka-test', function () {
            $dates = ['2026-01-01', '2026-01-02', '2026-01-03', '2026-01-04'];
            $balances = [10000, 10500, 10200, 11000];
            $response = Http::post('http://python_ml:8000/api/forecast', [
                'dates' => $dates, 'balances' => $balances, 'days_to_predict' => 2
            ]);
            if ($response->successful()) {
                return response()->json(['mesaj' => 'Başarılı.', 'tahminler' => $response->json('forecast')]);
            }
            return response()->json(['hata' => 'Bağlantı kurulamadı.'], 500);
        });

        Route::get('/gecmis-verileri-cek', function (\App\Services\VomsisService $vomsisService) {
            return "Sistem performansını korumak için geçmiş veri çekme otomasyonu panel üzerinden yönetilmelidir.";
        });
    });
    
    // ========================================================================
    // FİZİKSEL POS (TERMİNAL) ROTALARI
    // ========================================================================
    Route::get('/fiziksel-pos', [\App\Http\Controllers\PhysicalPosController::class, 'index'])->name('physical_pos.index');
    Route::get('/fiziksel-pos/senkronize', [\App\Http\Controllers\PhysicalPosController::class, 'sync'])->name('physical_pos.sync');
    
    // YENİ EKLENEN ROTA: Belirli bir cihazın içindeki hareketleri çeker
    Route::get('/fiziksel-pos/{id}/islemleri-cek', [\App\Http\Controllers\PhysicalPosController::class, 'syncTransactions'])->name('physical_pos.sync_transactions');
    // FİZİKSEL POS İŞLEMLERİNİ GÖRÜNTÜLEME EKRANI
    Route::get('/fiziksel-pos/{id}/islemler', [\App\Http\Controllers\PhysicalPosController::class, 'showTransactions'])->name('physical_pos.transactions');
    Route::post('/islem/toplu-etiket-ekle', [App\Http\Controllers\TagController::class, 'bulkAttachTags']);
    Route::post('/banka-ayarlari-kaydet', [App\Http\Controllers\DashboardController::class, 'updateBankSettings'])->name('bank.settings.update');
    Route::post('/islem/toplu-etiket-cikar', [App\Http\Controllers\TagController::class, 'bulkDetachTags']);
    // ========================================================================
    // 7. YAPAY ZEKA ASİSTANI (AI CHATBOT)
    // ========================================================================
    Route::post('/ai/ask', [App\Http\Controllers\AiController::class, 'ask'])->name('ai.ask');

    // ========================================================================
    // DEBUG: Dashboard vs Analytics karşılaştırması (GEÇİCİ - Sorun çözülünce SİL)
    // ========================================================================
    Route::get('/dev/debug-balances', function () {
        $user = auth()->user();
        
        // Dashboard'un kullandığı hesaplar
        $accs = \App\Models\BankAccount::where('include_in_totals', true)
            ->where('is_visible', true)
            ->whereHas('bank', fn($q) => $q->where('is_visible', true))
            ->get();
        $accountIds = $accs->pluck('id')->toArray();

        // --- DASHBOARD HESABI (kronolojik: transaction_date DESC) ---
        $dashBalances = [];
        foreach ($accountIds as $accId) {
            $bal = \App\Models\Transaction::where('bank_account_id', $accId)
                ->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->value('balance');
            if ($bal !== null) {
                $dashBalances[$accId] = $bal;
            }
        }

        // --- ANALİTİK HESABI (kronolojik: transaction_date DESC, aynı yöntem) ---
        $maxDbDateStr = \App\Models\Transaction::whereIn('bank_account_id', $accountIds)->max('transaction_date');
        $endDateStr = $maxDbDateStr ? Carbon::parse($maxDbDateStr)->format('Y-m-d') : Carbon::now()->format('Y-m-d');

        $rows = [];
        $dashTotalTL = 0; $anaTotalTL = 0;
        $dashTotalUSD = 0; $anaTotalUSD = 0;
        $dashTotalEUR = 0; $anaTotalEUR = 0;

        foreach ($accs as $acc) {
            $cur = $acc->currency;
            // Dashboard bakiyesi
            $dashBal = floatval($dashBalances[$acc->id] ?? $acc->balance);

            // Analytics bakiyesi: Tüm işlemleri kronolojik sırayla al, son bakiyeyi bul
            $lastTxn = \App\Models\Transaction::where('bank_account_id', $acc->id)
                ->where('transaction_date', '<=', $endDateStr . ' 23:59:59')
                ->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->first(['balance', 'transaction_date']);
            
            if ($lastTxn) {
                $anaBal = floatval($lastTxn->balance);
                $lastInRange = $lastTxn->transaction_date;
            } else {
                $anaBal = floatval($acc->balance ?? 0);
                $lastInRange = 'N/A';
            }

            $diff = round($dashBal - $anaBal, 2);
            $txnCount = \App\Models\Transaction::where('bank_account_id', $acc->id)->count();

            $rows[] = [
                'acc_id' => $acc->id,
                'currency' => $cur,
                'acc_name' => $acc->account_name ?? $acc->iban ?? 'Hesap #'.$acc->id,
                'dash_bal' => number_format($dashBal, 2),
                'ana_bal' => number_format($anaBal, 2),
                'diff' => number_format($diff, 2),
                'txn_count' => $txnCount,
                'chrono_last_date' => $lastInRange,
                'PROBLEM' => abs($diff) > 0.01 ? '❌ FARKLI' : '✅',
            ];

            if ($cur === 'TL' || $cur === 'TRY') { $dashTotalTL += $dashBal; $anaTotalTL += $anaBal; }
            if ($cur === 'USD') { $dashTotalUSD += $dashBal; $anaTotalUSD += $anaBal; }
            if ($cur === 'EUR') { $dashTotalEUR += $dashBal; $anaTotalEUR += $anaBal; }
        }

        return response()->json([
            'debug_info' => [
                'analytics_end' => $endDateStr,
                'max_db_date' => $maxDbDateStr,
                'total_accounts' => count($accountIds),
                'is_real_context' => $user->is_real_data,
            ],
            'totals' => [
                'TL' => ['dashboard' => number_format($dashTotalTL, 2), 'analytics' => number_format($anaTotalTL, 2), 'diff' => number_format($dashTotalTL - $anaTotalTL, 2)],
                'USD' => ['dashboard' => number_format($dashTotalUSD, 2), 'analytics' => number_format($anaTotalUSD, 2), 'diff' => number_format($dashTotalUSD - $anaTotalUSD, 2)],
                'EUR' => ['dashboard' => number_format($dashTotalEUR, 2), 'analytics' => number_format($anaTotalEUR, 2), 'diff' => number_format($dashTotalEUR - $anaTotalEUR, 2)],
            ],
            'accounts' => $rows,
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    });

    // ========================================================================
    // 8. ANALİZ ROTALARI
    // ========================================================================
    Route::get('/analizler', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analizler/pie-data', [AnalyticsController::class, 'pieData'])->name('analytics.pie-data');
    Route::get('/analizler/line-data', [AnalyticsController::class, 'lineData'])->name('analytics.line-data');
    Route::get('/analizler/line-data', [AnalyticsController::class, 'lineData'])->name('analytics.line-data');

});

// ========================================================================
// PUBLIC (3D Secure CALLBACK) - Banka/ACS cross-site POST yapar, auth yok
// ========================================================================
Route::post('/odeme/sonuc', [PaymentController::class, 'callback'])->name('payment.callback');

