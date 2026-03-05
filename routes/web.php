<?php

use Illuminate\Support\Facades\Route;
use App\Services\VomsisService;
use App\Http\Controllers\DashboardController;
use App\Jobs\SyncVomsisJob;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\PaymentController; 
use App\Http\Controllers\TagController;

// Anasayfaya girenleri direkt Dashboard'a yönlendir
Route::get('/', function () {
    return redirect('/dashboard');
});

// ========================================================================
// 1. DASHBOARD & VERİ SENKRONİZASYONU
// ========================================================================
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/arka-planda-cek', function () {
    // İşi kuyruğa fırlat (Dispatch) ve kullanıcıyı anında dashboard'a geri yolla.
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
Route::post('/odeme/sonuc', [PaymentController::class, 'callback'])->name('payment.callback');

// ========================================================================
// 6. GELİŞTİRİCİ TEST ROTALARI
// ========================================================================
Route::get('/test-token', function (VomsisService $vomsisService) {
    try {
        $token = $vomsisService->getToken();
        return "Tebrikler! Vomsis'e başarıyla bağlandın. İşte Token: " . $token;
    } catch (\Exception $e) {
        return "Bir hata oluştu: " . $e->getMessage();
    }
});

Route::get('/test-banks', function (VomsisService $vomsisService) {
    try {
        $mesaj = $vomsisService->syncBanks();
        return "İşlem Tamamlandı: " . $mesaj;
    } catch (\Exception $e) {
        return "Bir hata oluştu: " . $e->getMessage();
    }
});

Route::get('/test-accounts', function (VomsisService $vomsisService) {
    try {
        return $vomsisService->syncAccounts();
    } catch (\Exception $e) {
        return "Bir hata oluştu: " . $e->getMessage();
    }
});

Route::get('/test-transactions', function (VomsisService $vomsisService) {
    try {
        return $vomsisService->syncTransactions();
    } catch (\Exception $e) {
        return "Bir hata oluştu: " . $e->getMessage();
    }
});

Route::get('/test-types', function (VomsisService $vomsisService) {
    try {
        return $vomsisService->syncTransactionTypes();
    } catch (\Exception $e) {
        return "Hata: " . $e->getMessage();
    }
});

Route::get('/hata-test', function (VomsisService $vomsisService) {
    try {
        $vomsisService->syncTransactions();
        return "İşlem Başarılı! Hiç hata yok.";
    } catch (\Exception $e) {
        return "HATA SEBEBİ: " . $e->getMessage() . " <br> SATIR: " . $e->getLine() . " <br> DOSYA: " . $e->getFile();
    }
});