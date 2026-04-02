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

    // ========================================================================
    // 4. DIŞA AKTARIM (EXCEL & PDF TABLO)
    // ========================================================================
    Route::get('/export/pdf', [ExportController::class, 'exportPdf'])->name('export.pdf');
    Route::post('/export/excel', [ExportController::class, 'exportExcel'])->name('export.excel');

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
    // 6. GELİŞTİRİCİ TEST ROTALARI
    // ========================================================================
    Route::get('/test-token', function (VomsisService $vomsisService) {
        try {
            return "Tebrikler! Token: " . $vomsisService->getToken();
        } catch (\Exception $e) { return "Hata: " . $e->getMessage(); }
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

    Route::get('/hata-test', function (VomsisService $vomsisService) {
        try {
            $vomsisService->syncTransactions();
            return "İşlem Başarılı!";
        } catch (\Exception $e) { return "HATA: " . $e->getMessage(); }
    });

    Route::get('/test-poses', function (VomsisService $vomsisService) {
        try { return $vomsisService->syncVirtualPoses(); } catch (\Exception $e) { return "Hata: " . $e->getMessage(); }
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

    //
    //ANALİZ ROTALARI
    //




    Route::get('/analizler', [AnalyticsController::class, 'index'])->name('analytics.index');

    //-----------------------------------------------------------------------
    //      test roları
    //-----------------------------------------------------------------------

    Route::get('/yapay-zeka-test', function () {
    // 1. Örnek Geçmiş Veriler (Bunu daha sonra veritabanından çekeceğiz)
    $dates = ['2026-01-01', '2026-01-02', '2026-01-03', '2026-01-04', '2026-01-05', '2026-01-06', '2026-01-07', '2026-01-08', '2026-01-09', '2026-01-10'];
    $balances = [10000, 10500, 10200, 11000, 10800, 11500, 11200, 12000, 11800, 12500];

    // 2. Python Servisine İstek At
    // Docker kullandığımız için adres localhost değil, servis adı olan "python_ml" olur!
    $response = Http::post('http://python_ml:8000/api/forecast', [
        'dates' => $dates,
        'balances' => $balances,
        'days_to_predict' => 7 // Gelecek 7 günü tahmin et
    ]);

    // 3. Sonucu Ekrana Bas
    if ($response->successful()) {
        return response()->json([
            'mesaj' => 'Mükemmel! Laravel ve Python başarıyla konuştu.',
            'tahminler' => $response->json('forecast')
        ]);
    }

    return response()->json([
        'hata' => 'Bağlantı kurulamadı.', 
        'detay' => $response->body()
    ], 500);
});



Route::get('/gecmis-verileri-cek', function (\App\Services\VomsisService $vomsisService) {
    // Başlangıç ve Bitiş tarihlerini belirle (Örn: 4 haftalık bir periyot)
    $startDate = Carbon::parse('2025-12-09'); 
    $endDate   = Carbon::parse('2026-01-06'); // Yaklaşık 4 hafta sonrası

    $currentStart = $startDate->copy();
    $log = [];

    while ($currentStart->lessThan($endDate)) {
        // 7 günlük pencere oluştur
        $currentEnd = $currentStart->copy()->addDays(6); 
        
        // Eğer 7 gün eklediğimizde bitiş tarihini geçiyorsa, bitiş tarihine sabitle
        if ($currentEnd->greaterThan($endDate)) {
            $currentEnd = $endDate;
        }

        $baslangicStr = $currentStart->format('Y-m-d');
        $bitisStr     = $currentEnd->format('Y-m-d');

        try {
            // DİKKAT: VomsisService içindeki metodunun tarih parametresi aldığına emin ol.
            // Örnek: $vomsisService->syncTransactions($baslangicStr, $bitisStr);
            
            // Eğer senin metodun tarih almıyorsa, VomsisService içine gidip o fonksiyona 
            // $startDate ve $endDate parametreleri eklemen gerekecek.
            
            $log[] = "{$baslangicStr} ile {$bitisStr} arası başarıyla çekildi.";
        } catch (\Exception $e) {
            $log[] = "HATA ({$baslangicStr} - {$bitisStr}): " . $e->getMessage();
        }

        // Vomsis API'sini boğmamak için her istekten sonra 2 saniye bekle (Çok Önemli!)
        sleep(2); 

        // Bir sonraki döngü için başlangıç tarihini, mevcut bitişin 1 gün sonrasına al
        $currentStart = $currentEnd->copy()->addDay();
    }

    return $log; // İşlem bitince ekrana raporu bas
});
 });

// ========================================================================
// PUBLIC (3D Secure CALLBACK) - Banka/ACS cross-site POST yapar, auth yok
// ========================================================================
Route::post('/odeme/sonuc', [PaymentController::class, 'callback'])->name('payment.callback');