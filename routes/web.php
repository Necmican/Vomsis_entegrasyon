<?php

use App\Services\VomsisService;
use App\Http\Controllers\DashboardController;
use App\Jobs\SyncVomsisJob;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\PaymentController; 


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

Route::get('/test-accounts', function (App\Services\VomsisService $vomsisService) {
    try {
        return $vomsisService->syncAccounts();
    } catch (\Exception $e) {
        return "Bir hata oluştu: " . $e->getMessage();
    }
});

Route::get('/test-transactions', function (App\Services\VomsisService $vomsisService) {
    try {
        return $vomsisService->syncTransactions();
    } catch (\Exception $e) {
        return "Bir hata oluştu: " . $e->getMessage();
    }
});

Route::get('/dashboard', [DashboardController::class, 'index']);

Route::get('/arka-planda-cek', function () {
    // İşi kuyruğa fırlat (Dispatch) ve kullanıcıyı anında dashboard'a geri yolla.
    SyncVomsisJob::dispatch();
    
    return redirect('/dashboard')->with('mesaj', 'Veri çekme işlemi arka planda başlatıldı! Sayfayı birazdan yenileyin.');
});

Route::get('/test-types', function (App\Services\VomsisService $vomsisService) {
    try {
        return $vomsisService->syncTransactionTypes();
    } catch (\Exception $e) {
        return "Hata: " . $e->getMessage();
    }
});
Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/hata-test', function (App\Services\VomsisService $vomsisService) {
    try {
        // Sadece işlemleri çekmeyi deneyelim, bakalım nerede patlıyor?
        $vomsisService->syncTransactions();
        return "İşlem Başarılı! Hiç hata yok.";
    } catch (\Exception $e) {
        // Eğer patlarsa hatanın ne olduğunu ekrana yaz!
        return "HATA SEBEBİ: " . $e->getMessage() . " <br> SATIR: " . $e->getLine() . " <br> DOSYA: " . $e->getFile();
    }
});

Route::get('/export/pdf', [ExportController::class, 'exportPdf'])->name('export.pdf');
Route::post('/export/excel', [ExportController::class, 'exportExcel'])->name('export.excel');

Route::get('/odeme-yap', [PaymentController::class, 'index'])->name('payment.index');
Route::post('/odeme-yap', [PaymentController::class, 'process'])->name('payment.process');

Route::get('/sanal-pos-islemleri', [PaymentController::class, 'transactionsList'])->name('payment.list');

Route::post('/odeme/bin-check', [PaymentController::class, 'binCheck'])->name('payment.bincheck');


// Müşteri formu doldurup butona bastığında çalışacak rota (3D İsteğini atacağımız yer)
Route::post('/odeme/isle', [PaymentController::class, 'process'])->name('payment.process');
Route::post('/odeme/sonuc', [PaymentController::class, 'callback'])->name('payment.callback');